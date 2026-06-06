<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    protected $table = 'employees';

    public const ACTIVE_STATUS_ACTIVE = 'Active';
    public const ACTIVE_STATUS_NON_ACTIVE = 'Non Active';
    public const ACTIVE_STATUS_RESIGN = 'Resign';
    public const ACTIVE_STATUS_PHK = 'PHK';
    public const ACTIVE_STATUS_HABIS_KONTRAK = 'Habis Kontrak';
    public const ACTIVE_STATUS_MUTASI = 'Mutasi';

    protected $fillable = [
        'company_id',
        'placement_company_id',
        'nik',
        'nik_ktp',
        'address_ktp',
        'domicile_address',
        'name',
        'active_status',
        'place_of_birth',
        'date_of_birth',
        'phone',
        'emergency_contact_number',
        'npwp',
        'bank_name',
        'bank_account_no',
        'ptkp_status',
        'employment_status',
        'employee_type',
        'department',
        'position',
        'grade',
        'join_date',
        'contract_end',
        'photo_path',
        'ktp_path',
        'ijazah_path',
        'surat_lamaran_path',
        'cv_file_path',
        'mcu_file_path',
        'kk_path',
        'npwp_path',
        'skck_path',
    ];

    public $timestamps = false;

    public static function activeStatusOptions(): array
    {
        if (Schema::hasTable('employee_active_statuses')) {
            $items = EmployeeActiveStatus::query()
                ->where('company_id', 0)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('status_name')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all();
            if (!empty($items)) {
                return $items;
            }
        }

        return [
            self::ACTIVE_STATUS_ACTIVE,
            self::ACTIVE_STATUS_NON_ACTIVE,
            self::ACTIVE_STATUS_MUTASI,
            self::ACTIVE_STATUS_RESIGN,
            self::ACTIVE_STATUS_PHK,
            self::ACTIVE_STATUS_HABIS_KONTRAK,
        ];
    }

    public static function archiveActiveStatuses(): array
    {
        if (Schema::hasTable('employee_active_statuses')) {
            $items = EmployeeActiveStatus::query()
                ->where('company_id', 0)
                ->where('is_archive', 1)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('status_name')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all();
            if (!empty($items)) {
                return $items;
            }
        }

        return [
            self::ACTIVE_STATUS_MUTASI,
            self::ACTIVE_STATUS_RESIGN,
            self::ACTIVE_STATUS_PHK,
            self::ACTIVE_STATUS_HABIS_KONTRAK,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function placementCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'placement_company_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function getCompanyNameAttribute(): string
    {
        return (string) optional($this->company)->company_name;
    }

    public static function companyCodeForName(?string $companyName): string
    {
        $name = strtolower(trim((string) $companyName));
        $map = [
            'pt. berkah cipta persada' => 'BK',
            'pt. bina control power' => 'BN',
            'pt. keihindo inti elsys' => 'KI',
            'pt. resource mitra bersama' => 'RM',
        ];
        return $map[$name] ?? '';
    }

    public static function generateNik(int $companyId, ?string $joinDate, ?int $ignoreId = null): string
    {
        $company = Company::find($companyId);
        $code = self::companyCodeForName($company->company_name ?? '');
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)($company->company_name ?? '')), 0, 2));
        }

        $ts = $joinDate ? strtotime($joinDate) : time();
        $month = date('m', $ts);
        $year = date('y', $ts);
        $prefix = $code . $month . $year;

        $query = self::query()
            ->where('company_id', $companyId)
            ->where('nik', 'like', $prefix . '%');
        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        $lastNik = (string) $query->orderBy('nik', 'desc')->value('nik');
        $lastSeq = 0;
        if ($lastNik !== '' && strlen($lastNik) >= 4) {
            $lastSeq = (int) substr($lastNik, -4);
        }
        $nextSeq = str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);
        return $prefix . $nextSeq;
    }
}
