<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeActiveStatus extends Model
{
    protected $table = 'employee_active_statuses';

    protected $fillable = [
        'company_id',
        'status_name',
        'is_archive',
        'sort_order',
        'note',
    ];

    public $timestamps = false;
}

