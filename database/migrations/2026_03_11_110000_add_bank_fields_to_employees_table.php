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
            if (!Schema::hasColumn('employees', 'bank_name')) {
                $table->string('bank_name', 120)->nullable()->after('npwp');
            }
            if (!Schema::hasColumn('employees', 'bank_account_no')) {
                $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'bank_account_no')) {
                $table->dropColumn('bank_account_no');
            }
            if (Schema::hasColumn('employees', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
        });
    }
};
