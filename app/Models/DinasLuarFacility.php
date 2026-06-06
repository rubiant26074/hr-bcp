<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DinasLuarFacility extends Model
{
    protected $table = 'dinas_luar_facilities';

    protected $fillable = [
        'request_id',
        'name',
        'funded_by',
        'amount',
    ];
}
