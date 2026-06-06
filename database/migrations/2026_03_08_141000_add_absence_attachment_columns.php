<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absence_requests')) {
            Schema::table('absence_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('absence_requests', 'attachment_path')) {
                    $table->string('attachment_path', 255)->nullable()->after('reason');
                }
                if (!Schema::hasColumn('absence_requests', 'doctor_note_path')) {
                    $table->string('doctor_note_path', 255)->nullable()->after('attachment_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absence_requests')) {
            Schema::table('absence_requests', function (Blueprint $table) {
                if (Schema::hasColumn('absence_requests', 'doctor_note_path')) {
                    $table->dropColumn('doctor_note_path');
                }
                if (Schema::hasColumn('absence_requests', 'attachment_path')) {
                    $table->dropColumn('attachment_path');
                }
            });
        }
    }
};
