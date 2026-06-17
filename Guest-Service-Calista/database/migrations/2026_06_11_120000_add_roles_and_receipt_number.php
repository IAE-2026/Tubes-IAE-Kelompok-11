<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    
    public function up(): void
    {
        
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });

           
            DB::table('roles')->insert([
                ['name' => 'warga', 'description' => 'Role for citizens logged in via SSO', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'm2m', 'description' => 'Role for machine-to-machine integrations', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'admin', 'description' => 'Role for administrators', 'created_at' => now(), 'updated_at' => now()],
            ]);
        } else {
            
            foreach (['warga', 'm2m', 'admin'] as $role) {
                if (DB::table('roles')->where('name', $role)->count() === 0) {
                    DB::table('roles')->insert([
                        'name' => $role,
                        'description' => "Role for {$role}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        
        if (!Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
            });
        }

        
        if (!Schema::hasColumn('guests', 'receipt_number')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->string('receipt_number', 50)->nullable()->after('phone_number');
            });
        }
    }

    
    public function down(): void
    {
        if (Schema::hasColumn('guests', 'receipt_number')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->dropColumn('receipt_number');
            });
        }

        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('roles');
    }
};
