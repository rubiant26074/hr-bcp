<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPph21Request extends Model
{
    protected $table = 'payroll_pph21_requests';

    protected $fillable = [
        'company_id',
        'period_id',
        'requester_user_id',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_note',
    ];
}
