<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DinasLuarOther extends Model
{
    protected $table = 'dinas_luar_others';

    protected $fillable = [
        'request_id',
        'name',
        'amount',
    ];
}
