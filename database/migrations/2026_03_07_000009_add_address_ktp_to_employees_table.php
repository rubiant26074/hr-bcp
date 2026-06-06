<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || Schema::hasColumn('employees', 'address_ktp')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->text('address_ktp')->nullable()->after('nik_ktp');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'address_ktp')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('address_ktp');
        });
    }
};
