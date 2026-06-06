<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequestStep extends Model
{
    protected $table = 'approval_request_steps';

    protected $fillable = [
        'module_key',
        'request_id',
        'step_no',
        'approver_user_id',
        'status',
        'approved_by',
        'approved_at',
        'signature',
    ];
}
