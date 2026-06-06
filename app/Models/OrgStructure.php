<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgStructure extends Model
{
    protected $table = 'org_structures';

    protected $fillable = [
        'company_id',
        'name',
        'parent_id',
        'note',
        'sort_order',
    ];
}
