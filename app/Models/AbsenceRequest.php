<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceRequest extends Model
{
    protected $table = 'absence_requests';

    protected $fillable = [
        'company_id',
        'employee_id',
        'requester_user_id',
        'request_type',
        'date_start',
        'date_end',
        'reason',
        'attachment_path',
        'doctor_note_path',
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
