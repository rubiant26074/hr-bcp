<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLocation extends Model
{
    protected $table = 'attendance_locations';

    protected $fillable = [
        'company_id',
        'location_name',
        'latitude',
        'longitude',
        'radius_m',
    ];
}
