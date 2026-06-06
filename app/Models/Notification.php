<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'message',
        'link',
        'is_read',
    ];
}
