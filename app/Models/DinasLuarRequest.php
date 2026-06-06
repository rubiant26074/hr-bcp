<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DinasLuarRequest extends Model
{
    protected $table = 'dinas_luar_requests';

    protected $fillable = [
        'company_id',
        'requester_user_id',
        'employee_id',
        'request_type',
        'doc_no',
        'request_date',
        'work_start',
        'work_end',
        'extension_no',
        'customer',
        'work_order_no',
        'project',
        'pekerjaan',
        'lokasi',
        'country',
        'city',
        'passport_no',
        'passport_expiry',
        'currency',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_note',
    ];
}
