<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'work_days_per_week')) {
                $table->unsignedTinyInteger('work_days_per_week')->default(5)->after('payroll_day');
            }
            if (!Schema::hasColumn('companies', 'work_time_start')) {
                $table->time('work_time_start')->nullable()->after('work_days_per_week');
            }
            if (!Schema::hasColumn('companies', 'work_time_end')) {
                $table->time('work_time_end')->nullable()->after('work_time_start');
            }
            if (!Schema::hasColumn('companies', 'work_duration_hours')) {
                $table->decimal('work_duration_hours', 4, 2)->default(8)->after('work_time_end');
            }
            if (!Schema::hasColumn('companies', 'work_days_json')) {
                $table->text('work_days_json')->nullable()->after('work_duration_hours');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'work_days_json')) {
                $table->dropColumn('work_days_json');
            }
            if (Schema::hasColumn('companies', 'work_duration_hours')) {
                $table->dropColumn('work_duration_hours');
            }
            if (Schema::hasColumn('companies', 'work_time_end')) {
                $table->dropColumn('work_time_end');
            }
            if (Schema::hasColumn('companies', 'work_time_start')) {
                $table->dropColumn('work_time_start');
            }
            if (Schema::hasColumn('companies', 'work_days_per_week')) {
                $table->dropColumn('work_days_per_week');
            }
        });
    }
};
