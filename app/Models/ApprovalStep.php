<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $table = 'approval_steps';

    protected $fillable = [
        'company_id',
        'module_key',
        'requester_user_id',
        'step_no',
        'approver_user_id',
    ];
}
