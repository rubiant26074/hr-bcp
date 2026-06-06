<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalSetting extends Model
{
    protected $table = 'approval_settings';

    protected $fillable = [
        'company_id',
        'module_key',
        'requester_user_id',
        'approver1_user_id',
        'approver2_user_id',
        'step1_type',
        'step1_role',
        'step2_role',
    ];

    public $timestamps = false;
}
