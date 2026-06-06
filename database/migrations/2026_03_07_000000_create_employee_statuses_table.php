<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('status_name', 100);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_statuses');
    }
};
