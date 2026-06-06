<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleDefinition extends Model
{
    protected $table = 'role_definitions';

    protected $fillable = [
        'name',
        'description',
    ];
}
