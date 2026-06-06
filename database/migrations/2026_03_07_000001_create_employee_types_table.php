<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_name', 50);
            $table->timestamps();

            $table->unique('type_name');
        });

        DB::table('employee_types')->insert([
            ['type_name' => 'Staf', 'created_at' => now(), 'updated_at' => now()],
            ['type_name' => 'Non Staf', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};
