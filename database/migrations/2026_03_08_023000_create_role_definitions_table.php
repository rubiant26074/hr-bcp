<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('role_definitions')) {
            Schema::create('role_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('role_definitions')->count() === 0) {
            DB::table('role_definitions')->insert([
                ['name' => 'Super Admin', 'description' => 'Full access', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'CEO', 'description' => 'Global approval/read-only', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'CFA', 'description' => 'Global finance approval/read-only', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'HR', 'description' => 'HR operations', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Finance', 'description' => 'Payroll & finance operations', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Employee', 'description' => 'Employee self-service', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_definitions');
    }
};
