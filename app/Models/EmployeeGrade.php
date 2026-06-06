<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeGrade extends Model
{
    protected $table = 'employee_grades';

    protected $fillable = [
        'company_id',
        'grade_name',
        'note',
    ];
}
