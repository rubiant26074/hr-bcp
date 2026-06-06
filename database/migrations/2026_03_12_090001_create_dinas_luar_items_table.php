<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dinas_luar_lumpsums')) {
            Schema::create('dinas_luar_lumpsums', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->string('name', 150);
                $table->unsignedInteger('days')->default(1);
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['request_id']);
            });
        }

        if (!Schema::hasTable('dinas_luar_facilities')) {
            Schema::create('dinas_luar_facilities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->string('name', 150);
                $table->string('funded_by', 80)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['request_id']);
            });
        }

        if (!Schema::hasTable('dinas_luar_others')) {
            Schema::create('dinas_luar_others', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');
                $table->string('name', 150);
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['request_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dinas_luar_others');
        Schema::dropIfExists('dinas_luar_facilities');
        Schema::dropIfExists('dinas_luar_lumpsums');
    }
};
