<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new columns to employees table
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'domicile_address')) {
                $table->text('domicile_address')->nullable()->after('address_ktp');
            }
            if (!Schema::hasColumn('employees', 'emergency_contact_number')) {
                $table->string('emergency_contact_number', 50)->nullable();
            }
            if (!Schema::hasColumn('employees', 'mcu_file_path')) {
                $table->string('mcu_file_path')->nullable();
            }
            if (!Schema::hasColumn('employees', 'cv_file_path')) {
                $table->string('cv_file_path')->nullable();
            }
        });

        // Create new table for HRD documents
        if (!Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('employee_id')->constrained()->onDelete('cascade');
                $table->string('document_name');
                $table->string('file_path');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop HRD documents table
        Schema::dropIfExists('employee_documents');

        // Remove columns from employees table
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $columns = ['domicile_address', 'emergency_contact_number', 'mcu_file_path', 'cv_file_path'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('employees', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
