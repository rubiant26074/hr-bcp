<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_locations')) {
            return;
        }

        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_locations')) {
            return;
        }

        Schema::table('attendance_locations', function (Blueprint $table) {
            $table->unique(['company_id']);
        });
    }
};
