<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityRosterService
{
    public static function ensureDefaultShiftDefinitionsForCompany(int $companyId): void
    {
        if ($companyId <= 0 || !Schema::hasTable('security_shift_definitions')) {
            return;
        }

        $rows = [
            ['code' => 'P', 'name' => 'PAGI', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'cross_day' => 0],
            ['code' => 'S', 'name' => 'SIANG', 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'cross_day' => 0],
            ['code' => 'M', 'name' => 'MALAM', 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'cross_day' => 1],
            ['code' => 'OFF', 'name' => 'OFF', 'start_time' => null, 'end_time' => null, 'cross_day' => 0],
        ];

        foreach ($rows as $row) {
            DB::table('security_shift_definitions')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'cross_day' => $row['cross_day'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private static function getShiftDefMap(int $companyId): array
    {
        self::ensureDefaultShiftDefinitionsForCompany($companyId);
        return DB::table('security_shift_definitions')
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->get(['code', 'start_time', 'end_time', 'cross_day'])
            ->keyBy('code')
            ->all();
    }

    private static function resolveShiftWindow(string $workDate, string $shiftCode, array $defs): array
    {
        $def = $defs[$shiftCode] ?? null;
        if (!$def || $shiftCode === 'OFF' || empty($def->start_time) || empty($def->end_time)) {
            return [null, null];
        }

        $startAt = $workDate . ' ' . substr((string) $def->start_time, 0, 8);
        $endDate = $workDate;
        if ((int) ($def->cross_day ?? 0) === 1) {
            $endDate = date('Y-m-d', strtotime($workDate . ' +1 day'));
        }
        $endAt = $endDate . ' ' . substr((string) $def->end_time, 0, 8);

        return [$startAt, $endAt];
    }

    private static function logChange(array $payload): void
    {
        if (!Schema::hasTable('security_roster_change_logs')) {
            return;
        }
        DB::table('security_roster_change_logs')->insert([
            'company_id' => $payload['company_id'],
            'roster_id' => $payload['roster_id'] ?? null,
            'employee_id' => $payload['employee_id'],
            'work_date' => $payload['work_date'],
            'action' => $payload['action'],
            'before_json' => $payload['before_json'] ?? null,
            'after_json' => $payload['after_json'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'instruction_ref' => $payload['instruction_ref'] ?? null,
            'actor_user_id' => $payload['actor_user_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    public static function upsertManualRoster(
        int $companyId,
        int $employeeId,
        string $workDate,
        string $shiftCode,
        ?string $reason = null,
        ?string $instructionRef = null,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use ($companyId, $employeeId, $workDate, $shiftCode, $reason, $instructionRef, $actorUserId) {
            $defs = self::getShiftDefMap($companyId);
            $shiftCode = strtoupper(trim($shiftCode));
            if (!isset($defs[$shiftCode]) && $shiftCode !== 'OFF') {
                throw new \RuntimeException('Shift code tidak valid.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
                throw new \RuntimeException('Format tanggal harus YYYY-MM-DD.');
            }

            [$startAt, $endAt] = self::resolveShiftWindow($workDate, $shiftCode, $defs);
            $existing = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->first();

            if ($existing) {
                DB::table('security_rosters')->where('id', $existing->id)->update([
                    'shift_code' => $shiftCode,
                    'shift_start_at' => $startAt,
                    'shift_end_at' => $endAt,
                    'source' => 'MANUAL',
                    'note' => $reason,
                    'version_no' => (int) ($existing->version_no ?? 0) + 1,
                    'updated_by' => $actorUserId,
                    'updated_at' => now(),
                ]);
                $after = DB::table('security_rosters')->where('id', $existing->id)->first();
                self::logChange([
                    'company_id' => $companyId,
                    'roster_id' => $existing->id,
                    'employee_id' => $employeeId,
                    'work_date' => $workDate,
                    'action' => 'UPDATE',
                    'before_json' => json_encode($existing, JSON_UNESCAPED_UNICODE),
                    'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE),
                    'reason' => $reason,
                    'instruction_ref' => $instructionRef,
                    'actor_user_id' => $actorUserId,
                ]);
                return ['ok' => true, 'mode' => 'update', 'id' => (int) $existing->id];
            }

            DB::table('security_rosters')->insert([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'shift_code' => $shiftCode,
                'shift_start_at' => $startAt,
                'shift_end_at' => $endAt,
                'source' => 'MANUAL',
                'note' => $reason,
                'version_no' => 1,
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $after = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->first();
            self::logChange([
                'company_id' => $companyId,
                'roster_id' => (int) ($after->id ?? 0) ?: null,
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'action' => 'CREATE',
                'before_json' => null,
                'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);
            return ['ok' => true, 'mode' => 'create', 'id' => (int) ($after->id ?? 0)];
        });
    }

    public static function deleteRoster(
        int $companyId,
        int $rosterId,
        ?string $reason = null,
        ?string $instructionRef = null,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use ($companyId, $rosterId, $reason, $instructionRef, $actorUserId) {
            $row = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->where('id', $rosterId)
                ->first();
            if (!$row) {
                throw new \RuntimeException('Data roster tidak ditemukan.');
            }

            DB::table('security_rosters')->where('id', $rosterId)->delete();
            self::logChange([
                'company_id' => $companyId,
                'roster_id' => $rosterId,
                'employee_id' => (int) ($row->employee_id ?? 0),
                'work_date' => (string) ($row->work_date ?? ''),
                'action' => 'DELETE',
                'before_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'after_json' => null,
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);

            return ['ok' => true];
        });
    }

    public static function deleteRosterBulk(
        int $companyId,
        array $rosterIds,
        ?string $reason = null,
        ?string $instructionRef = null,
        ?int $actorUserId = null
    ): array {
        $ids = array_values(array_filter(array_map('intval', $rosterIds), static fn ($id) => $id > 0));
        if (count($ids) === 0) {
            throw new \RuntimeException('Pilih minimal 1 roster untuk dihapus.');
        }

        return DB::transaction(function () use ($companyId, $ids, $reason, $instructionRef, $actorUserId) {
            $rows = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->get();

            if ($rows->isEmpty()) {
                throw new \RuntimeException('Data roster terpilih tidak ditemukan.');
            }

            DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->whereIn('id', $rows->pluck('id')->all())
                ->delete();

            foreach ($rows as $row) {
                self::logChange([
                    'company_id' => $companyId,
                    'roster_id' => (int) $row->id,
                    'employee_id' => (int) ($row->employee_id ?? 0),
                    'work_date' => (string) ($row->work_date ?? ''),
                    'action' => 'DELETE',
                    'before_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'after_json' => null,
                    'reason' => $reason,
                    'instruction_ref' => $instructionRef,
                    'actor_user_id' => $actorUserId,
                ]);
            }

            return ['ok' => true, 'deleted' => (int) $rows->count()];
        });
    }

    public static function applySwap(
        int $companyId,
        int $employeeA,
        int $employeeB,
        string $workDate,
        ?string $reason = null,
        ?string $instructionRef = null,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use ($companyId, $employeeA, $employeeB, $workDate, $reason, $instructionRef, $actorUserId) {
            $defs = self::getShiftDefMap($companyId);
            $a = DB::table('security_rosters')->where('company_id', $companyId)->where('employee_id', $employeeA)->where('work_date', $workDate)->first();
            $b = DB::table('security_rosters')->where('company_id', $companyId)->where('employee_id', $employeeB)->where('work_date', $workDate)->first();

            if (!$a || !$b) {
                throw new \RuntimeException('Roster salah satu personel belum ada pada tanggal tersebut.');
            }

            [$aStart, $aEnd] = self::resolveShiftWindow($workDate, (string) $b->shift_code, $defs);
            [$bStart, $bEnd] = self::resolveShiftWindow($workDate, (string) $a->shift_code, $defs);

            DB::table('security_rosters')->where('id', $a->id)->update([
                'shift_code' => (string) $b->shift_code,
                'shift_start_at' => $aStart,
                'shift_end_at' => $aEnd,
                'source' => 'SWAP',
                'note' => $reason,
                'version_no' => (int) ($a->version_no ?? 0) + 1,
                'updated_by' => $actorUserId,
                'updated_at' => now(),
            ]);

            DB::table('security_rosters')->where('id', $b->id)->update([
                'shift_code' => (string) $a->shift_code,
                'shift_start_at' => $bStart,
                'shift_end_at' => $bEnd,
                'source' => 'SWAP',
                'note' => $reason,
                'version_no' => (int) ($b->version_no ?? 0) + 1,
                'updated_by' => $actorUserId,
                'updated_at' => now(),
            ]);

            $aAfter = DB::table('security_rosters')->where('id', $a->id)->first();
            $bAfter = DB::table('security_rosters')->where('id', $b->id)->first();

            self::logChange([
                'company_id' => $companyId,
                'roster_id' => $a->id,
                'employee_id' => $employeeA,
                'work_date' => $workDate,
                'action' => 'SWAP',
                'before_json' => json_encode($a, JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode($aAfter, JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);
            self::logChange([
                'company_id' => $companyId,
                'roster_id' => $b->id,
                'employee_id' => $employeeB,
                'work_date' => $workDate,
                'action' => 'SWAP',
                'before_json' => json_encode($b, JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode($bAfter, JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);

            if (Schema::hasTable('security_shift_requests')) {
                DB::table('security_shift_requests')->insert([
                    'company_id' => $companyId,
                    'request_type' => 'SWAP',
                    'from_employee_id' => $employeeA,
                    'to_employee_id' => $employeeB,
                    'work_date' => $workDate,
                    'from_shift_code' => (string) $a->shift_code,
                    'to_shift_code' => (string) $b->shift_code,
                    'status' => 'APPLIED',
                    'reason' => $reason,
                    'instruction_ref' => $instructionRef,
                    'created_by' => $actorUserId,
                    'applied_by' => $actorUserId,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['ok' => true, 'employee_a_shift' => $aAfter->shift_code ?? null, 'employee_b_shift' => $bAfter->shift_code ?? null];
        });
    }

    public static function applyReplace(
        int $companyId,
        int $fromEmployee,
        int $toEmployee,
        string $workDate,
        ?string $reason = null,
        ?string $instructionRef = null,
        ?int $actorUserId = null
    ): array {
        return DB::transaction(function () use ($companyId, $fromEmployee, $toEmployee, $workDate, $reason, $instructionRef, $actorUserId) {
            $defs = self::getShiftDefMap($companyId);
            $from = DB::table('security_rosters')->where('company_id', $companyId)->where('employee_id', $fromEmployee)->where('work_date', $workDate)->first();
            if (!$from) {
                throw new \RuntimeException('Roster personel asal tidak ditemukan pada tanggal tersebut.');
            }

            [$toStart, $toEnd] = self::resolveShiftWindow($workDate, (string) $from->shift_code, $defs);

            $toExisting = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->where('employee_id', $toEmployee)
                ->where('work_date', $workDate)
                ->first();

            if ($toExisting) {
                DB::table('security_rosters')->where('id', $toExisting->id)->update([
                    'shift_code' => (string) $from->shift_code,
                    'shift_start_at' => $toStart,
                    'shift_end_at' => $toEnd,
                    'source' => 'REPLACE',
                    'note' => $reason,
                    'version_no' => (int) ($toExisting->version_no ?? 0) + 1,
                    'updated_by' => $actorUserId,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('security_rosters')->insert([
                    'company_id' => $companyId,
                    'employee_id' => $toEmployee,
                    'work_date' => $workDate,
                    'shift_code' => (string) $from->shift_code,
                    'shift_start_at' => $toStart,
                    'shift_end_at' => $toEnd,
                    'source' => 'REPLACE',
                    'note' => $reason,
                    'version_no' => 1,
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $toExisting = DB::table('security_rosters')
                    ->where('company_id', $companyId)
                    ->where('employee_id', $toEmployee)
                    ->where('work_date', $workDate)
                    ->first();
            }

            [$offStart, $offEnd] = self::resolveShiftWindow($workDate, 'OFF', $defs);
            DB::table('security_rosters')->where('id', $from->id)->update([
                'shift_code' => 'OFF',
                'shift_start_at' => $offStart,
                'shift_end_at' => $offEnd,
                'source' => 'REPLACE',
                'note' => $reason,
                'version_no' => (int) ($from->version_no ?? 0) + 1,
                'updated_by' => $actorUserId,
                'updated_at' => now(),
            ]);

            $fromAfter = DB::table('security_rosters')->where('id', $from->id)->first();
            $toAfter = DB::table('security_rosters')->where('id', $toExisting->id)->first();

            self::logChange([
                'company_id' => $companyId,
                'roster_id' => $from->id,
                'employee_id' => $fromEmployee,
                'work_date' => $workDate,
                'action' => 'REPLACE',
                'before_json' => json_encode($from, JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode($fromAfter, JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);
            self::logChange([
                'company_id' => $companyId,
                'roster_id' => $toExisting->id,
                'employee_id' => $toEmployee,
                'work_date' => $workDate,
                'action' => 'REPLACE',
                'before_json' => json_encode($toExisting, JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode($toAfter, JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'instruction_ref' => $instructionRef,
                'actor_user_id' => $actorUserId,
            ]);

            if (Schema::hasTable('security_shift_requests')) {
                DB::table('security_shift_requests')->insert([
                    'company_id' => $companyId,
                    'request_type' => 'REPLACE',
                    'from_employee_id' => $fromEmployee,
                    'to_employee_id' => $toEmployee,
                    'work_date' => $workDate,
                    'from_shift_code' => (string) $from->shift_code,
                    'to_shift_code' => (string) ($toAfter->shift_code ?? $from->shift_code),
                    'status' => 'APPLIED',
                    'reason' => $reason,
                    'instruction_ref' => $instructionRef,
                    'created_by' => $actorUserId,
                    'applied_by' => $actorUserId,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['ok' => true, 'from_employee_shift' => $fromAfter->shift_code ?? null, 'to_employee_shift' => $toAfter->shift_code ?? null];
        });
    }
}
