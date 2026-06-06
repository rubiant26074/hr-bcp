<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_request_steps')) {
            Schema::create('approval_request_steps', function (Blueprint $table) {
                $table->id();
                $table->string('module_key', 50);
                $table->unsignedBigInteger('request_id');
                $table->unsignedTinyInteger('step_no');
                $table->unsignedBigInteger('approver_user_id')->nullable();
                $table->string('status', 20)->default('Pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('signature', 120)->nullable();
                $table->timestamps();

                $table->unique(['module_key', 'request_id', 'step_no'], 'uniq_request_steps');
                $table->index(['module_key', 'request_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
    }
};
