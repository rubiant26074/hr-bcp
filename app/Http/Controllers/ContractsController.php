<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Contract;
use App\Services\EmployeeContractSyncService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ContractsController extends Controller
{
    private function parseContractNotes($rawNotes): array
    {
        $defaults = [
            'kontrak_terahir' => '',
            'kontrak_1' => '',
            'kotrak_2' => '',
            'rehat' => '',
            'kontrak_1_lanjutan' => '',
            'kotrak_2_lanjutan' => '',
        ];
        if (!is_string($rawNotes) || $rawNotes === '') {
            return ['masa_kontrak' => $defaults, 'notes_text' => ''];
        }
        $decoded = json_decode($rawNotes, true);
        if (!is_array($decoded)) {
            return ['masa_kontrak' => $defaults, 'notes_text' => $rawNotes];
        }
        $masaKontrak = $decoded['masa_kontrak'] ?? [];
        if (!is_array($masaKontrak)) {
            $masaKontrak = [];
        }
        return [
            'masa_kontrak' => array_merge($defaults, $masaKontrak),
            'notes_text' => (string)($decoded['notes_text'] ?? ''),
        ];
    }

    private function normalizeHeaderKey($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $h) {
            $key = $this->normalizeHeaderKey($h);
            if ($key !== '') {
                $map[$key] = $i;
            }
        }
        return $map;
    }

    private function normalizeNikKey($value): string
    {
        $value = strtoupper(trim((string) $value));
        return preg_replace('/[^A-Z0-9]/', '', $value);
    }

    private function importDateToDb($value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value) && trim($value) === '') {
            return '';
        }
        if (is_numeric($value) && class_exists(ExcelDate::class)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        return date_input_to_db((string) $value);
    }

    public function index(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('contracts.index');
        }
        $companyId = current_company_id();
        $messages = [];
        app(EmployeeContractSyncService::class)->syncCompanyEmployees($companyId);

        if ($request->isMethod('post')) {
            $action = $request->input('action', '');
            if ($action === 'delete') {
                $contractId = (int) $request->input('id', 0);
                $contract = $contractId ? Contract::find($contractId) : null;
                if ($contract && $contract->employee && (int)$contract->employee->company_id === (int)$companyId) {
                    $contract->delete();
                }
                return redirect()->route('contracts.index');
            } elseif ($action === 'bulk_delete') {
                $ids = $request->input('delete_ids', []);
                if (!is_array($ids) || count($ids) === 0) {
                    $messages[] = 'Pilih minimal 1 contract untuk dihapus.';
                } else {
                    $deleted = 0;
                    foreach ($ids as $rawId) {
                        $contractId = (int) $rawId;
                        if ($contractId <= 0) {
                            continue;
                        }
                        $contract = Contract::find($contractId);
                        if ($contract && $contract->employee && (int)$contract->employee->company_id === (int)$companyId) {
                            $contract->delete();
                            $deleted++;
                        }
                    }
                    $messages[] = "Bulk delete selesai: {$deleted} contract dihapus.";
                }
            }
        }

        $companies = Company::orderBy('id')->get();
        $baseQuery = Contract::with('employee')
            ->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->orderByDesc('id');

        $contractsActive = (clone $baseQuery)
            ->whereHas('employee', function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('employment_status')
                      ->orWhere('employment_status', 'not like', '%TETAP%');
                });
            })
            ->get();

        $contractsArchive = (clone $baseQuery)
            ->whereHas('employee', function ($q) {
                $q->where('employment_status', 'like', '%TETAP%');
            })
            ->get();

        return view('modules.contracts.index', compact(
            'user',
            'companyId',
            'companies',
            'contractsActive',
            'contractsArchive',
            'messages'
        ));
    }

    public function form(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('id')) {
                $params['id'] = (int) $request->query('id');
            }
            return redirect()->route('contracts.form', $params);
        }
        $companyId = current_company_id();

        $messages = [];
        $companies = Company::orderBy('id')->get();
        $employees = Employee::where('company_id', $companyId)->orderBy('id')->get();
        $employeeIds = array_map(static function ($e) {
            return (int) $e->id;
        }, $employees->all());

        $id = (int) ($request->query('id') ?? $request->input('id') ?? 0);
        $edit = $id ? Contract::find($id) : null;
        if ($id && !$edit) {
            abort(404, 'Contract not found');
        }
        if ($edit) {
            $editEmployee = $edit->employee;
            if (!$editEmployee || (int)$editEmployee->company_id !== (int)$companyId) {
                abort(404, 'Contract not found');
            }
        }
        $editNotes = $this->parseContractNotes($edit ? $edit->notes : '');

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'save');
            if ($action === 'import_contract') {
                if (!$request->hasFile('import_file')) {
                    $messages[] = 'Upload file import gagal.';
                } else {
                    $file = $request->file('import_file');
                    if (!$file->isValid()) {
                        $messages[] = 'Upload file import gagal.';
                    } elseif ($file->getSize() > 5 * 1024 * 1024) {
                        $messages[] = 'File terlalu besar (maks 5MB).';
                    } else {
                        $ext = strtolower($file->getClientOriginalExtension());
                        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
                            $messages[] = 'Format harus CSV atau Excel (XLSX/XLS).';
                        } else {
                            $rows = [];
                            if ($ext === 'csv') {
                                $handle = fopen($file->getPathname(), 'r');
                                if ($handle) {
                                    while (($row = fgetcsv($handle)) !== false) {
                                        $rows[] = $row;
                                    }
                                    fclose($handle);
                                }
                            } else {
                                if (!class_exists(\ZipArchive::class)) {
                                    $messages[] = 'Ekstensi PHP ZIP belum aktif. Aktifkan extension=zip di php.ini atau gunakan file CSV.';
                                } else {
                                    $spreadsheet = IOFactory::load($file->getPathname());
                                    $sheet = $spreadsheet->getActiveSheet();
                                    $rows = $sheet->toArray(null, true, true, false);
                                }
                            }
                            if (empty($messages)) {
                                if (count($rows) === 0) {
                                    $messages[] = 'File kosong.';
                                } else {
                                    $header = $rows[0];
                                    $headerMap = $this->buildHeaderMap($header);
                                    $required = ['nik', 'contract type', 'start date'];
                                    foreach ($required as $req) {
                                        if (!isset($headerMap[$req])) {
                                            $messages[] = 'Header wajib minimal: NIK, Contract Type, Start Date.';
                                            break;
                                        }
                                    }
                                }
                            }

                            if (empty($messages)) {
                                $employeeByNik = [];
                                foreach ($employees as $e) {
                                    $employeeByNik[$this->normalizeNikKey($e->nik)] = [
                                        'id' => (int) $e->id,
                                        'join_date' => (string)($e->join_date ?? ''),
                                    ];
                                }
                                $created = 0;
                                $skipped = 0;
                                $ignored = 0;
                                $skipReasons = [];
                                for ($r = 1; $r < count($rows); $r++) {
                                    $row = $rows[$r];
                                    $nikRaw = (string)($row[$headerMap['nik']] ?? '');
                                    $contractTypeRaw = (string)($row[$headerMap['contract type']] ?? '');
                                    $startDateRaw = $row[$headerMap['start date']] ?? '';
                                    $endDateRaw = isset($headerMap['end date']) ? ($row[$headerMap['end date']] ?? '') : '';

                                    $allEmpty = trim($nikRaw) === '' && trim($contractTypeRaw) === '';
                                    if (is_string($startDateRaw)) {
                                        $allEmpty = $allEmpty && trim($startDateRaw) === '';
                                    } else {
                                        $allEmpty = $allEmpty && ($startDateRaw === null);
                                    }
                                    if (is_string($endDateRaw)) {
                                        $allEmpty = $allEmpty && trim($endDateRaw) === '';
                                    } else {
                                        $allEmpty = $allEmpty && ($endDateRaw === null);
                                    }
                                    if ($allEmpty) {
                                        $ignored++;
                                        continue;
                                    }

                                    $nik = $this->normalizeNikKey($row[$headerMap['nik']] ?? '');
                                    $employeeInfo = $employeeByNik[$nik] ?? null;
                                    $employeeId = (int)($employeeInfo['id'] ?? 0);
                                    $contractType = trim((string)($row[$headerMap['contract type']] ?? ''));
                                    if ($contractType === '') {
                                        $contractType = 'Tetap';
                                    }
                                    $startDate = $this->importDateToDb($row[$headerMap['start date']] ?? '');
                                    if ($startDate === '' && !empty($employeeInfo['join_date'])) {
                                        $startDate = date_input_to_db($employeeInfo['join_date']);
                                    }
                                    $endDate = isset($headerMap['end date']) ? $this->importDateToDb($row[$headerMap['end date']] ?? '') : '';

                                    $masaKontrak = [
                                        'kontrak_terahir' => isset($headerMap['kontrak terahir']) ? $this->importDateToDb($row[$headerMap['kontrak terahir']] ?? '') : '',
                                        'kontrak_1' => isset($headerMap['kontrak i']) ? $this->importDateToDb($row[$headerMap['kontrak i']] ?? '') : '',
                                        'kotrak_2' => isset($headerMap['kotrak ii']) ? $this->importDateToDb($row[$headerMap['kotrak ii']] ?? '') : '',
                                        'rehat' => isset($headerMap['rehat']) ? $this->importDateToDb($row[$headerMap['rehat']] ?? '') : '',
                                        'kontrak_1_lanjutan' => isset($headerMap['kontrak i lanjutan']) ? $this->importDateToDb($row[$headerMap['kontrak i lanjutan']] ?? '') : '',
                                        'kotrak_2_lanjutan' => isset($headerMap['kotrak ii lanjutan']) ? $this->importDateToDb($row[$headerMap['kotrak ii lanjutan']] ?? '') : '',
                                    ];
                                    $notesText = isset($headerMap['notes']) ? trim((string)($row[$headerMap['notes']] ?? '')) : '';

                                    $invalidMasa = false;
                                    foreach ($masaKontrak as $mv) {
                                        if ($mv === null) {
                                            $invalidMasa = true;
                                            break;
                                        }
                                    }

                                    if ($employeeId <= 0 || $startDate === '' || $startDate === null || $endDate === null) {
                                        $skipped++;
                                        if ($employeeId <= 0) {
                                            $skipReasons[] = 'Baris ' . ($r + 1) . ': NIK tidak ditemukan di company aktif (' . (string)($row[$headerMap['nik']] ?? '') . ').';
                                        } elseif ($startDate === '' || $startDate === null) {
                                            $skipReasons[] = 'Baris ' . ($r + 1) . ': Start Date tidak valid.';
                                        } elseif ($endDate === null) {
                                            $skipReasons[] = 'Baris ' . ($r + 1) . ': End Date tidak valid.';
                                        }
                                        continue;
                                    }

                                    if ($invalidMasa) {
                                        $masaKontrak = array_map(static function ($v) {
                                            return $v === null ? '' : $v;
                                        }, $masaKontrak);
                                    }

                                    $masaKontrak = array_map(static function ($v) {
                                        return $v === null ? '' : $v;
                                    }, $masaKontrak);

                                    Contract::create([
                                        'employee_id' => $employeeId,
                                        'contract_type' => $contractType,
                                        'start_date' => $startDate,
                                        'end_date' => $endDate === '' ? null : $endDate,
                                        'notes' => json_encode(
                                            ['masa_kontrak' => $masaKontrak, 'notes_text' => $notesText],
                                            JSON_UNESCAPED_UNICODE
                                        ),
                                    ]);
                                    $created++;
                                }

                                $messages[] = "Import selesai: {$created} data masuk, {$skipped} data dilewati, {$ignored} baris kosong diabaikan.";
                                if (!empty($skipReasons)) {
                                    $preview = array_slice($skipReasons, 0, 5);
                                    foreach ($preview as $reason) {
                                        $messages[] = $reason;
                                    }
                                    if (count($skipReasons) > 5) {
                                        $messages[] = 'Masih ada ' . (count($skipReasons) - 5) . ' baris lain yang dilewati.';
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $validated = $request->validate([
                    'employee_id' => ['required','integer'],
                    'contract_type' => ['required','string','max:50'],
                    'start_date' => ['required','string'],
                    'end_date' => ['nullable','string'],
                    'notes' => ['nullable','string'],
                    'masa_kontrak_terahir' => ['nullable','string'],
                    'masa_kontrak_1' => ['nullable','string'],
                    'masa_kotrak_2' => ['nullable','string'],
                    'masa_rehat' => ['nullable','string'],
                    'masa_kontrak_1_lanjutan' => ['nullable','string'],
                    'masa_kotrak_2_lanjutan' => ['nullable','string'],
                ]);

                $startDate = date_input_to_db($validated['start_date']);
                $endDate = date_input_to_db($validated['end_date'] ?? '');
                $data = [
                    'employee_id' => (int) $validated['employee_id'],
                    'contract_type' => trim((string) $validated['contract_type']),
                    'start_date' => $startDate === '' ? null : $startDate,
                    'end_date' => $endDate === '' ? null : $endDate,
                    'notes' => '',
                ];
                $masaKontrak = [
                    'kontrak_terahir' => date_input_to_db($validated['masa_kontrak_terahir'] ?? ''),
                    'kontrak_1' => date_input_to_db($validated['masa_kontrak_1'] ?? ''),
                    'kotrak_2' => date_input_to_db($validated['masa_kotrak_2'] ?? ''),
                    'rehat' => date_input_to_db($validated['masa_rehat'] ?? ''),
                    'kontrak_1_lanjutan' => date_input_to_db($validated['masa_kontrak_1_lanjutan'] ?? ''),
                    'kotrak_2_lanjutan' => date_input_to_db($validated['masa_kotrak_2_lanjutan'] ?? ''),
                ];
                $notesText = trim((string) ($validated['notes'] ?? ''));

                if ($data['employee_id'] <= 0) {
                    $messages[] = 'Employee wajib dipilih.';
                }
                if (!in_array($data['employee_id'], $employeeIds, true)) {
                    $messages[] = 'Employee tidak valid untuk company aktif.';
                }
                if ($data['contract_type'] === '' || $startDate === '') {
                    $messages[] = 'Contract Type dan Start Date wajib diisi (dd/mm/yyyy).';
                }
                if ($startDate === null) {
                    $messages[] = 'Format Start Date harus dd/mm/yyyy.';
                }
                if ($endDate === null) {
                    $messages[] = 'Format End Date harus dd/mm/yyyy.';
                }
                foreach ($masaKontrak as $masaValue) {
                    if ($masaValue === null) {
                        $messages[] = 'Format Masa Kontrak harus tanggal valid (dd/mm/yyyy).';
                        break;
                    }
                }

                $masaKontrak = array_map(static function ($v) {
                    return $v === null ? '' : $v;
                }, $masaKontrak);
                $data['notes'] = json_encode(
                    ['masa_kontrak' => $masaKontrak, 'notes_text' => $notesText],
                    JSON_UNESCAPED_UNICODE
                );

                if (empty($messages)) {
                    if ($id) {
                        $edit->fill($data);
                        $edit->save();
                    } else {
                        Contract::create($data);
                    }
                    return redirect()->route('contracts.index');
                }
            }
        }

        return view('modules.contracts.form', compact('user', 'companyId', 'companies', 'employees', 'edit', 'editNotes', 'messages'));
    }

    public function template()
    {
        if (!class_exists(\ZipArchive::class)) {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['NIK', 'Contract Type', 'Start Date', 'End Date', 'Kontrak Terahir', 'Kontrak I', 'Kotrak II', 'Rehat', 'Kontrak I Lanjutan', 'Kotrak II Lanjutan', 'Notes']);
            fputcsv($handle, ['BK03220002', 'PKWT', '01/03/2026', '31/08/2026', '01/03/2025', '01/09/2025', '01/12/2025', '01/01/2026', '01/02/2026', '01/03/2026', 'Catatan kontrak']);
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="contracts_template.csv"');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contracts Template');
        $headers = [
            'NIK',
            'Contract Type',
            'Start Date',
            'End Date',
            'Kontrak Terahir',
            'Kontrak I',
            'Kotrak II',
            'Rehat',
            'Kontrak I Lanjutan',
            'Kotrak II Lanjutan',
            'Notes',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sample = [
            'BK03220002',
            'PKWT',
            '01/03/2026',
            '31/08/2026',
            '01/03/2025',
            '01/09/2025',
            '01/12/2025',
            '01/01/2026',
            '01/02/2026',
            '01/03/2026',
            'Catatan kontrak',
        ];
        $sheet->fromArray($sample, null, 'A2');
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $temp = tempnam(sys_get_temp_dir(), 'contracts');
        $writer->save($temp);
        return response()->download($temp, 'contracts_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

