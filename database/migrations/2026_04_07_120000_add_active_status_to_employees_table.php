<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || Schema::hasColumn('employees', 'active_status')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('active_status', 20)->default('Active')->after('name');
        });

        DB::table('employees')
            ->whereNull('active_status')
            ->orWhere('active_status', '')
            ->update(['active_status' => 'Active']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'active_status')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('active_status');
        });
    }
};
