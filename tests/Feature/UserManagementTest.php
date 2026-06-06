<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role');
            $table->string('password_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('nik')->nullable();
            $table->string('name');
        });

        Schema::create('role_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('role_definitions')->insert([
            ['name' => 'Super Admin'],
            ['name' => 'CEO'],
            ['name' => 'CFA'],
            ['name' => 'HR'],
        ]);
    }

    public function test_admin_can_delete_other_user(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'Super Admin'],
            ['id' => 2, 'name' => 'Target', 'email' => 'target@example.com', 'role' => 'HR'],
        ]);

        $response = $this
            ->withSession([
                'user' => [
                    'id' => 1,
                    'company_id' => null,
                    'employee_id' => null,
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                    'role' => 'Super Admin',
                ],
            ])
            ->post('/users', [
                'action' => 'delete',
                'id' => 2,
            ]);

        $response->assertRedirect('/users?deleted=1');
        $this->assertDatabaseMissing('users', ['id' => 2]);
    }

    public function test_admin_can_create_global_ceo_user_without_company(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'Super Admin'],
        ]);

        $response = $this
            ->withSession([
                'user' => [
                    'id' => 1,
                    'company_id' => null,
                    'employee_id' => null,
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                    'role' => 'Super Admin',
                ],
            ])
            ->post('/users/form', [
                'name' => 'CEO Group',
                'email' => 'ceo@example.com',
                'password' => 'secret123',
                'role' => 'CEO',
                'company_id' => '',
                'employee_id' => '',
            ]);

        $response->assertRedirect('/users?created=1');
        $this->assertDatabaseHas('users', [
            'email' => 'ceo@example.com',
            'role' => 'CEO',
            'company_id' => null,
            'employee_id' => null,
        ]);
    }
}
