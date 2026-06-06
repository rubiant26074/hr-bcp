<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ktp_path', 255)->nullable()->after('photo_path');
            $table->string('ijazah_path', 255)->nullable()->after('ktp_path');
            $table->string('kk_path', 255)->nullable()->after('ijazah_path');
            $table->string('npwp_path', 255)->nullable()->after('kk_path');
            $table->string('skck_path', 255)->nullable()->after('npwp_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['ktp_path', 'ijazah_path', 'kk_path', 'npwp_path', 'skck_path']);
        });
    }
};
