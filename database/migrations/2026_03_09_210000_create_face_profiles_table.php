<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_profiles')) {
            Schema::create('face_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->json('descriptor');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('face_profiles');
    }
};
