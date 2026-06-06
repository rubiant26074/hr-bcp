<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'phone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'phone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
