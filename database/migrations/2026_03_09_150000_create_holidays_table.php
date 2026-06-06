<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->date('holiday_date');
                $table->string('name', 120)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'holiday_date']);
                $table->index(['company_id', 'holiday_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
