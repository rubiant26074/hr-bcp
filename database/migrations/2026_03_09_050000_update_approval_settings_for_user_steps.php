<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_settings')) {
            return;
        }

        Schema::table('approval_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_settings', 'requester_user_id')) {
                $table->unsignedBigInteger('requester_user_id')->nullable()->after('module_key');
            }
            if (!Schema::hasColumn('approval_settings', 'approver1_user_id')) {
                $table->unsignedBigInteger('approver1_user_id')->nullable()->after('requester_user_id');
            }
            if (!Schema::hasColumn('approval_settings', 'approver2_user_id')) {
                $table->unsignedBigInteger('approver2_user_id')->nullable()->after('approver1_user_id');
            }
        });

        Schema::table('approval_settings', function (Blueprint $table) {
            $table->dropUnique('uniq_company_module');
        });

        Schema::table('approval_settings', function (Blueprint $table) {
            $table->unique(['company_id', 'module_key', 'requester_user_id'], 'uniq_company_module_requester');
            $table->index(['company_id', 'module_key', 'requester_user_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('approval_settings')) {
            return;
        }

        Schema::table('approval_settings', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'module_key', 'requester_user_id']);
            $table->dropUnique('uniq_company_module_requester');
        });

        Schema::table('approval_settings', function (Blueprint $table) {
            $table->unique(['company_id', 'module_key'], 'uniq_company_module');
        });

        Schema::table('approval_settings', function (Blueprint $table) {
            if (Schema::hasColumn('approval_settings', 'approver2_user_id')) {
                $table->dropColumn('approver2_user_id');
            }
            if (Schema::hasColumn('approval_settings', 'approver1_user_id')) {
                $table->dropColumn('approver1_user_id');
            }
            if (Schema::hasColumn('approval_settings', 'requester_user_id')) {
                $table->dropColumn('requester_user_id');
            }
        });
    }
};
