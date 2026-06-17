<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the 'roles' table for local role-based access control.
     * Pre-seeds with default roles used by the Federated SSO module.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Unique role identifier, e.g., admin, viewer');
            $table->string('display_name')->nullable()->comment('Human-readable role name');
            $table->text('description')->nullable()->comment('Brief description of role permissions');
            $table->timestamps();
        });

        // Pre-seed default roles so the application works out-of-the-box
        DB::table('roles')->insert([
            [
                'name'         => 'admin',
                'display_name' => 'Administrator',
                'description'  => 'Full administrative access to all resources.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'name'         => 'viewer',
                'display_name' => 'Viewer',
                'description'  => 'Read-only access to catalog resources.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
