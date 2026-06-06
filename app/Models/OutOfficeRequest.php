<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutOfficeRequest extends Model
{
    protected $table = 'out_office_requests';

    protected $fillable = [
        'company_id',
        'employee_id',
        'requester_user_id',
        'date',
        'time_start',
        'time_end',
        'destination',
        'reason',
        'status',
        'atasan_approved_by',
        'atasan_approved_at',
        'atasan_signature',
        'hrd_approved_by',
        'hrd_approved_at',
        'hrd_signature',
        'rejected_by',
        'rejected_at',
        'rejected_note',
    ];
}
