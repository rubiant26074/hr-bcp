<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'placement_company_id')) {
                $table->unsignedBigInteger('placement_company_id')->nullable()->after('company_id');
                $table->index('placement_company_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'placement_company_id')) {
                $table->dropIndex(['placement_company_id']);
                $table->dropColumn('placement_company_id');
            }
        });
    }
};

