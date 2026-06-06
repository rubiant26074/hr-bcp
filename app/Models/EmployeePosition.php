<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePosition extends Model
{
    protected $table = 'employee_positions';

    protected $fillable = [
        'company_id',
        'position_name',
        'note',
    ];
}
