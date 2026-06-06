<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDepartment extends Model
{
    protected $table = 'employee_departments';

    protected $fillable = [
        'company_id',
        'department_name',
        'note',
    ];
}
