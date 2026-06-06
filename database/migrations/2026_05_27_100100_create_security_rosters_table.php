<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_rosters')) {
            return;
        }

        Schema::create('security_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->string('shift_code', 10);
            $table->dateTime('shift_start_at')->nullable();
            $table->dateTime('shift_end_at')->nullable();
            $table->string('source', 20)->default('GENERATED');
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->unique(['company_id', 'employee_id', 'work_date']);
            $table->index(['company_id', 'work_date']);
            $table->index(['company_id', 'shift_code', 'work_date']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_rosters');
    }
};
