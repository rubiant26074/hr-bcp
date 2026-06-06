<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'company_name',
        'company_code',
        'address',
        'npwp',
        'bank_name',
        'bank_debit_account_no',
        'logo_path',
        'bpjs_health_pct',
        'bpjs_jht_pct',
        'bpjs_jp_pct',
        'payroll_day',
        'work_days_per_week',
        'work_time_start',
        'work_time_end',
        'work_duration_hours',
        'work_days_json',
        'payroll_absence_mode',
        'payroll_manual_present_days',
        'allin_ot_rate_6_7',
        'allin_ot_rate_7_8',
        'allin_ot_rate_8_9',
        'allin_ot_rate_9_10',
    ];

    public $timestamps = false;
}
