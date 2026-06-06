<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'must_verify_email')) {
            $afterColumn = null;
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $afterColumn = 'email_verified_at';
            } elseif (Schema::hasColumn('users', 'email')) {
                $afterColumn = 'email';
            }

            Schema::table('users', function (Blueprint $table) use ($afterColumn) {
                $column = $table->boolean('must_verify_email')->default(false);
                if ($afterColumn) {
                    $column->after($afterColumn);
                }
            });
        }

        if (!Schema::hasTable('email_verification_tokens')) {
            Schema::create('email_verification_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('token_hash', 64);
                $table->dateTime('expires_at');
                $table->dateTime('used_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'token_hash'], 'email_verification_user_token_unique');
                $table->index(['user_id', 'expires_at'], 'email_verification_user_exp_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_verification_tokens')) {
            Schema::drop('email_verification_tokens');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'must_verify_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('must_verify_email');
            });
        }
    }
};
