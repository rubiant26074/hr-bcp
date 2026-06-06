<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    protected $table = 'employee_statuses';

    protected $fillable = [
        'company_id',
        'status_name',
        'note',
    ];
}
