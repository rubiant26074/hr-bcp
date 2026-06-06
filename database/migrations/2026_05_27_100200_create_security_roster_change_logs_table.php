<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_roster_change_logs')) {
            return;
        }

        Schema::create('security_roster_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('roster_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->string('action', 20); // CREATE, UPDATE, DELETE, SWAP, REPLACE
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('instruction_ref', 255)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['company_id', 'work_date']);
            $table->index('employee_id');
            $table->index('roster_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_roster_change_logs');
    }
};
