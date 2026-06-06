<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_steps')) {
            Schema::create('approval_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('module_key', 50);
                $table->unsignedBigInteger('requester_user_id');
                $table->unsignedTinyInteger('step_no');
                $table->unsignedBigInteger('approver_user_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'module_key', 'requester_user_id', 'step_no'], 'uniq_approval_steps');
                $table->index(['company_id', 'module_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
