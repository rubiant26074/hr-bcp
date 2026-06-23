<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_daily')) {
            return;
        }

        Schema::table('attendance_daily', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_daily', 'overtime_hours_manual')) {
                $table->decimal('overtime_hours_manual', 8, 2)->nullable()->after('overtime_hours');
            }
            if (!Schema::hasColumn('attendance_daily', 'overtime_hours_is_manual')) {
                $table->boolean('overtime_hours_is_manual')->default(false)->after('overtime_hours_manual');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_daily')) {
            return;
        }

        Schema::table('attendance_daily', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_daily', 'overtime_hours_is_manual')) {
                $table->dropColumn('overtime_hours_is_manual');
            }
            if (Schema::hasColumn('attendance_daily', 'overtime_hours_manual')) {
                $table->dropColumn('overtime_hours_manual');
            }
        });
    }
};
