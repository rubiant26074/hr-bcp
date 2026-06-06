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
            if (!Schema::hasColumn('companies', 'allin_ot_rate_6_7')) {
                $table->decimal('allin_ot_rate_6_7', 12, 2)->default(30000)->after('work_days_json');
            }
            if (!Schema::hasColumn('companies', 'allin_ot_rate_7_8')) {
                $table->decimal('allin_ot_rate_7_8', 12, 2)->default(35000)->after('allin_ot_rate_6_7');
            }
            if (!Schema::hasColumn('companies', 'allin_ot_rate_8_9')) {
                $table->decimal('allin_ot_rate_8_9', 12, 2)->default(40000)->after('allin_ot_rate_7_8');
            }
            if (!Schema::hasColumn('companies', 'allin_ot_rate_9_10')) {
                $table->decimal('allin_ot_rate_9_10', 12, 2)->default(45000)->after('allin_ot_rate_8_9');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            foreach (['allin_ot_rate_6_7', 'allin_ot_rate_7_8', 'allin_ot_rate_8_9', 'allin_ot_rate_9_10'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
