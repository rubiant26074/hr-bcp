<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Employee;

class EmployeeContractSyncService
{
    public const AUTO_CONTRACT_NOTE = 'Auto sinkron dari Master Employees';

    public function isContractEmploymentStatus(?string $employmentStatus): bool
    {
        $value = mb_strtolower(trim((string) $employmentStatus));
        if ($value === '') {
            return false;
        }

        return str_contains($value, 'kontrak')
            || str_contains($value, 'contract')
            || str_contains($value, 'contracts');
    }

    public function syncCompanyEmployees(int $companyId): int
    {
        $synced = 0;

        Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('employment_status')
            ->where('employment_status', '<>', '')
            ->orderBy('id')
            ->chunk(200, function ($employees) use (&$synced) {
                foreach ($employees as $employee) {
                    if ($this->syncEmployee($employee)) {
                        $synced++;
                    }
                }
            });

        return $synced;
    }

    public function syncEmployee(Employee $employee): bool
    {
        if (!$this->isContractEmploymentStatus($employee->employment_status ?? '')) {
            return false;
        }

        $startDate = date_input_to_db((string) ($employee->join_date ?? ''));
        if ($startDate === '' || $startDate === null) {
            $startDate = date('Y-m-d');
        }

        $endDate = date_input_to_db((string) ($employee->contract_end ?? ''));
        $contractType = trim((string) ($employee->employment_status ?? ''));
        $notes = json_encode([
            'masa_kontrak' => [
                'kontrak_terahir' => '',
                'kontrak_1' => '',
                'kotrak_2' => '',
                'rehat' => '',
                'kontrak_1_lanjutan' => '',
                'kotrak_2_lanjutan' => '',
            ],
            'notes_text' => self::AUTO_CONTRACT_NOTE,
        ], JSON_UNESCAPED_UNICODE);

        $exact = Contract::query()
            ->where('employee_id', $employee->id)
            ->where('contract_type', $contractType)
            ->where('start_date', $startDate)
            ->where(function ($query) use ($endDate) {
                if ($endDate === '' || $endDate === null) {
                    $query->whereNull('end_date');
                } else {
                    $query->where('end_date', $endDate);
                }
            })
            ->first();

        if ($exact) {
            if ($this->isAutoSyncedContract($exact)) {
                $exact->notes = $notes;
                $exact->save();
            }
            return false;
        }

        $latest = Contract::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->first();

        if ($this->isAutoSyncedContract($latest)) {
            $latest->contract_type = $contractType;
            $latest->start_date = $startDate;
            $latest->end_date = ($endDate === '' || $endDate === null) ? null : $endDate;
            $latest->notes = $notes;
            $latest->save();
            return false;
        }

        Contract::create([
            'employee_id' => $employee->id,
            'contract_type' => $contractType,
            'start_date' => $startDate,
            'end_date' => ($endDate === '' || $endDate === null) ? null : $endDate,
            'notes' => $notes,
        ]);

        return true;
    }

    private function isAutoSyncedContract(?Contract $contract): bool
    {
        if (!$contract || !is_string($contract->notes) || $contract->notes === '') {
            return false;
        }

        $decoded = json_decode($contract->notes, true);
        if (is_array($decoded)) {
            return trim((string) ($decoded['notes_text'] ?? '')) === self::AUTO_CONTRACT_NOTE;
        }

        return trim((string) $contract->notes) === self::AUTO_CONTRACT_NOTE;
    }
}
