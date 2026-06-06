<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaveController extends Controller
{
    private function buildLeaveRows(int $companyId, int $quota): array
    {
        $employees = Employee::where('company_id', $companyId)->orderBy('id')->get();
        $usedMap = $this->cutiUsageByEmployee($companyId);

        $rows = [];
        foreach ($employees as $e) {
            $used = (int) ($usedMap[$e->id] ?? 0);
            $remaining = max(0, $quota - $used);
            $rows[] = [
                'employee' => $e,
                'used' => $used,
                'remaining' => $remaining,
                'eligible' => $this->eligibleForCuti($e->join_date ?? null),
            ];
        }

        return [$employees, $rows];
    }

    private function annualQuota(): int
    {
        return 12;
    }

    private function yearWindow(): array
    {
        $year = (int) date('Y');
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        return [$year, $start, $end];
    }

    private function cutiUsageByEmployee(int $companyId): array
    {
        [$year, $start, $end] = $this->yearWindow();
        $holidaySet = Holiday::where('company_id', 0)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('holiday_date')
            ->map(static fn ($d) => (string) $d)
            ->flip()
            ->all();
        $rows = AbsenceRequest::where('company_id', $companyId)
            ->where('request_type', 'Cuti')
            ->where('status', 'Approved')
            ->whereDate('date_end', '>=', $start->toDateString())
            ->whereDate('date_start', '<=', $end->toDateString())
            ->get(['employee_id', 'date_start', 'date_end', 'reason']);

        $map = [];
        foreach ($rows as $row) {
            $employeeId = (int) ($row->employee_id ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $reasonText = strtolower(trim((string) ($row->reason ?? '')));
            $isCutiBersama = str_contains($reasonText, 'cuti bersama') || str_contains($reasonText, 'cuti lebaran');
            $rangeStart = Carbon::parse($row->date_start)->startOfDay();
            $rangeEnd = Carbon::parse($row->date_end)->endOfDay();
            if ($rangeEnd->lt($start) || $rangeStart->gt($end)) {
                continue;
            }
            if ($rangeStart->lt($start)) {
                $rangeStart = $start->copy();
            }
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }
            $days = 0;
            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $key = $cursor->toDateString();
                // Cuti bersama tetap mengurangi hak cuti tahunan walaupun tanggalnya hari libur.
                if ($isCutiBersama || !isset($holidaySet[$key])) {
                    $days++;
                }
                $cursor->addDay();
            }
            $map[$employeeId] = ($map[$employeeId] ?? 0) + $days;
        }
        return $map;
    }

    private function eligibleForCuti(?string $joinDate): bool
    {
        if (!$joinDate) {
            return false;
        }
        $joined = Carbon::parse($joinDate)->startOfDay();
        return $joined->addYear()->lte(Carbon::now());
    }

    public function index(Request $request)
    {
        $user = current_user();
        $role = (string) ($user['role'] ?? '');
        $isAdmin = $role === 'Super Admin';
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $hasGlobalScope = current_user_has_global_scope($user);

        $selectedCompanyId = $companyId;
        if ($hasGlobalScope) {
            $selectedCompanyId = (int) $request->input('target_company_id', $request->query('company_id', $companyId));
            if ($selectedCompanyId < 0 || ($selectedCompanyId > 0 && !Company::where('id', $selectedCompanyId)->exists())) {
                $selectedCompanyId = $companyId;
            }
        }

        $quota = $this->annualQuota();
        $recapCompanyId = $selectedCompanyId > 0 ? $selectedCompanyId : $companyId;
        [$employees, $rows] = $this->buildLeaveRows($recapCompanyId, $quota);

        $messages = [];
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'action' => ['required','string'],
                'date_start' => ['required','date'],
                'date_end' => ['required','date'],
                'reason' => ['nullable','string','max:255'],
                'target_company_id' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($data['action'] === 'generate_lebaran') {
                $targetCompanyId = $selectedCompanyId;
                if ($hasGlobalScope && isset($data['target_company_id'])) {
                    $targetCompanyId = (int) $data['target_company_id'];
                    if ($targetCompanyId < 0 || ($targetCompanyId > 0 && !Company::where('id', $targetCompanyId)->exists())) {
                        return back()->withErrors(['target_company_id' => 'Entitas perusahaan tidak valid.'])->withInput();
                    }
                }
                $selectedCompanyId = $targetCompanyId;

                $dateStart = Carbon::parse($data['date_start'])->startOfDay();
                $dateEnd = Carbon::parse($data['date_end'])->startOfDay();
                if ($dateEnd->lt($dateStart)) {
                    return back()->withErrors(['date_end' => 'Tanggal selesai harus >= tanggal mulai.'])->withInput();
                }
                $reason = trim((string) ($data['reason'] ?? 'Cuti Lebaran'));
                if ($reason === '') {
                    $reason = 'Cuti Lebaran';
                }

                $targetCompanyIds = [];
                if ($targetCompanyId === 0 && $hasGlobalScope) {
                    $targetCompanyIds = Company::orderBy('id')->pluck('id')->map(static fn ($v) => (int) $v)->all();
                } else {
                    $targetCompanyIds = [$targetCompanyId > 0 ? $targetCompanyId : $companyId];
                }

                foreach ($targetCompanyIds as $cid) {
                    $created = 0;
                    $updated = 0;
                    $unchanged = 0;
                    $totalDays = 0;
                    $createdRequests = 0;
                    $updatedRequests = 0;

                    $cursor = $dateStart->copy();
                    while ($cursor->lte($dateEnd)) {
                        $totalDays++;
                        $date = $cursor->toDateString();
                        $existing = Holiday::where('company_id', 0)
                            ->where('holiday_date', $date)
                            ->first();
                        if ($existing) {
                            if (trim((string) ($existing->name ?? '')) !== $reason) {
                                $existing->name = $reason;
                                $existing->save();
                                $updated++;
                            } else {
                                $unchanged++;
                            }
                        } else {
                            Holiday::create([
                                'company_id' => 0,
                                'holiday_date' => $date,
                                'name' => $reason,
                            ]);
                            $created++;
                        }
                        $cursor->addDay();
                    }

                    $employeesTarget = Employee::where('company_id', $cid)->orderBy('id')->get();
                    // System-generated approved cuti for each employee so annual quota is reduced
                    // without requiring employee self-submission.
                    foreach ($employeesTarget as $emp) {
                        $existingReq = AbsenceRequest::where('company_id', $cid)
                            ->where('employee_id', (int) $emp->id)
                            ->where('request_type', 'Cuti')
                            ->whereDate('date_start', $dateStart->toDateString())
                            ->whereDate('date_end', $dateEnd->toDateString())
                            ->where('status', 'Approved')
                            ->first();

                        $requesterUserId = (int) ($user['id'] ?? 0);
                        $mappedUserId = (int) (User::where('employee_id', (int) $emp->id)->value('id') ?? 0);
                        if ($mappedUserId > 0) {
                            $requesterUserId = $mappedUserId;
                        }

                        if ($existingReq) {
                            $existingReq->reason = $reason;
                            $existingReq->atasan_approved_by = (int) ($user['id'] ?? 0);
                            $existingReq->atasan_approved_at = now();
                            $existingReq->atasan_signature = 'Approved';
                            $existingReq->hrd_approved_by = (int) ($user['id'] ?? 0);
                            $existingReq->hrd_approved_at = now();
                            $existingReq->hrd_signature = 'Approved';
                            $existingReq->save();
                            $updatedRequests++;
                        } else {
                            AbsenceRequest::create([
                                'company_id' => $cid,
                                'employee_id' => (int) $emp->id,
                                'requester_user_id' => $requesterUserId,
                                'request_type' => 'Cuti',
                                'date_start' => $dateStart->toDateString(),
                                'date_end' => $dateEnd->toDateString(),
                                'reason' => $reason,
                                'status' => 'Approved',
                                'atasan_approved_by' => (int) ($user['id'] ?? 0),
                                'atasan_approved_at' => now(),
                                'atasan_signature' => 'Approved',
                                'hrd_approved_by' => (int) ($user['id'] ?? 0),
                                'hrd_approved_at' => now(),
                                'hrd_signature' => 'Approved',
                            ]);
                            $createdRequests++;
                        }
                    }

                    $companyName = (string) (Company::where('id', $cid)->value('company_name') ?? ('Company #' . $cid));
                    $messages[] = "Generate cuti bersama berhasil untuk {$companyName}. Libur: total {$totalDays} hari (baru {$created}, update {$updated}, sama {$unchanged}). Kuota cuti: request approved dibuat {$createdRequests}, diupdate {$updatedRequests}.";
                }

                return redirect()->route('leave.index', ['company_id' => $selectedCompanyId > 0 ? $selectedCompanyId : 0])->with('leave_messages', $messages);
            } elseif ($data['action'] === 'reset_lebaran') {
                if (!$isAdmin) {
                    return back()->withErrors(['action' => 'Hanya admin yang dapat melakukan reset generated cuti.'])->withInput();
                }

                $targetCompanyId = $selectedCompanyId;
                if ($hasGlobalScope && isset($data['target_company_id'])) {
                    $targetCompanyId = (int) $data['target_company_id'];
                    if ($targetCompanyId <= 0 || !Company::where('id', $targetCompanyId)->exists()) {
                        return back()->withErrors(['target_company_id' => 'Entitas perusahaan tidak valid.'])->withInput();
                    }
                }

                // Full reset for generated cuti bersama/lebaran in selected company
                // so recap cuti terpakai from generated entries can return to 0.
                $deletedHolidays = Holiday::where('company_id', 0)
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(COALESCE(name, "")) LIKE ?', ['%cuti bersama%'])
                          ->orWhereRaw('LOWER(COALESCE(name, "")) LIKE ?', ['%cuti lebaran%']);
                    })
                    ->delete();

                $deletedRequests = AbsenceRequest::where('company_id', $targetCompanyId)
                    ->where('request_type', 'Cuti')
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(COALESCE(reason, "")) LIKE ?', ['%cuti bersama%'])
                          ->orWhereRaw('LOWER(COALESCE(reason, "")) LIKE ?', ['%cuti lebaran%'])
                          ->orWhereRaw('LOWER(COALESCE(reason, "")) LIKE ?', ['%lebaran%']);
                    })
                    ->delete();

                $companyName = (string) (Company::where('id', $targetCompanyId)->value('company_name') ?? ('Company #' . $targetCompanyId));
                $messages[] = "Reset generated cuti bersama selesai untuk {$companyName}. Holiday dihapus: {$deletedHolidays}, request cuti dihapus: {$deletedRequests}.";
                return redirect()->route('leave.index', ['company_id' => $targetCompanyId])->with('leave_messages', $messages);
            }
        }

        if (session()->has('leave_messages')) {
            $messages = (array) session('leave_messages');
        }

        return view('modules.leave.index', compact('user', 'employees', 'rows', 'quota', 'messages', 'companies', 'selectedCompanyId', 'hasGlobalScope', 'isAdmin'));
    }
}
