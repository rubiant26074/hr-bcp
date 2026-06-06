<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DinasLuarLumpsum extends Model
{
    protected $table = 'dinas_luar_lumpsums';

    protected $fillable = [
        'request_id',
        'name',
        'days',
        'amount',
        'total',
    ];
}
