<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absence_requests') && !Schema::hasColumn('absence_requests', 'attachment_path')) {
            Schema::table('absence_requests', function (Blueprint $table) {
                $table->string('attachment_path', 255)->nullable()->after('reason');
                $table->string('doctor_note_path', 255)->nullable()->after('attachment_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absence_requests') && Schema::hasColumn('absence_requests', 'doctor_note_path')) {
            Schema::table('absence_requests', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'doctor_note_path']);
            });
        }
    }
};
