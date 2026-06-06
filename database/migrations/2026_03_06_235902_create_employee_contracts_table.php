<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_contracts')) {
            return;
        }

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('contract_type', 50);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->longText('notes')->nullable();

            $table->index('employee_id');
            $table->index(['employee_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
