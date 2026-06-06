<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_period')) {
            return;
        }

        Schema::create('payroll_period', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status', 20)->default('Draft');

            $table->unique(['month', 'year'], 'uniq_payroll_period_month_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period');
    }
};
