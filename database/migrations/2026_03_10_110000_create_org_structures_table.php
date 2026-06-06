<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('org_structures')) {
            Schema::create('org_structures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 120);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['company_id']);
                $table->index(['company_id', 'parent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_structures');
    }
};
