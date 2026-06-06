<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('grade_name', 120);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'grade_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_grades');
    }
};
