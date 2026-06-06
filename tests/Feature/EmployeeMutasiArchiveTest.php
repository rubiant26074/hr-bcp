<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeMutasiArchiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_code')->nullable();
            $table->string('logo_path')->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('nik')->nullable();
            $table->string('nik_ktp')->nullable();
            $table->string('name');
            $table->string('active_status')->nullable();
            $table->string('phone')->nullable();
            $table->string('npwp')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('employee_type')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('grade')->nullable();
            $table->date('join_date')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('photo_path')->nullable();
        });

        Schema::create('employee_mutations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('from_company_id');
            $table->unsignedBigInteger('to_company_id');
            $table->string('from_nik')->nullable();
            $table->string('to_nik')->nullable();
            $table->dateTime('mutated_at')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('note')->nullable();
        });
    }

    public function test_mutasi_archive_uses_destination_from_mutation_record(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'company_name' => 'PT. Berkah Cipta Persada', 'company_code' => 'BK'],
            ['id' => 2, 'company_name' => 'PT. Resource Mitra Bersama', 'company_code' => 'RM'],
            ['id' => 3, 'company_name' => 'PT. Bina Control Power', 'company_code' => 'BN'],
        ]);

        // Employee currently sits in the latest company (Bina), but Berkah mutasi archive
        // must still show the original destination (Resource) for the 2026-04-09 mutation.
        DB::table('employees')->insert([
            'id' => 10,
            'company_id' => 3,
            'nik' => 'BN0406260001',
            'nik_ktp' => '123',
            'name' => 'Budi Rubiantoro',
            'active_status' => 'Active',
            'phone' => '0812',
            'npwp' => '09',
            'employment_status' => 'Tetap',
            'employee_type' => 'Staf',
            'department' => 'IT',
            'position' => 'Staff',
            'grade' => 'A',
            'join_date' => '2026-01-01',
            'contract_end' => null,
            'photo_path' => null,
        ]);

        DB::table('employee_mutations')->insert([
            [
                'id' => 1,
                'employee_id' => 10,
                'from_company_id' => 1,
                'to_company_id' => 2,
                'from_nik' => 'BK0406260001',
                'to_nik' => 'RM0406260001',
                'mutated_at' => '2026-04-09 09:00:00',
                'actor_user_id' => 99,
                'note' => null,
            ],
            [
                'id' => 2,
                'employee_id' => 10,
                'from_company_id' => 2,
                'to_company_id' => 3,
                'from_nik' => 'RM0406260001',
                'to_nik' => 'BN0406260001',
                'mutated_at' => '2026-04-10 09:00:00',
                'actor_user_id' => 99,
                'note' => null,
            ],
        ]);

        $response = $this
            ->withSession([
                'user' => [
                    'id' => 99,
                    'company_id' => 1,
                    'employee_id' => null,
                    'name' => 'HR',
                    'email' => 'hr@example.com',
                    'role' => 'Admin',
                ],
                'company_id' => 1,
            ])
            ->get('/employees?view=mutasi');

        $response->assertOk();
        $response->assertSee('PT. Resource Mitra Bersama');
        $response->assertSee('09/04/2026');
    }
}

