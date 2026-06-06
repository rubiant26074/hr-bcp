<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceProfile extends Model
{
    protected $table = 'face_profiles';

    protected $fillable = [
        'user_id',
        'descriptor',
    ];

    protected $casts = [
        'descriptor' => 'array',
    ];
}
