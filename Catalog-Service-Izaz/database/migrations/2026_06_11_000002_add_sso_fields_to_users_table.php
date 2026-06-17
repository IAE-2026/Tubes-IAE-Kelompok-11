<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Extends the 'users' table to support Federated SSO authentication.
     *
     * - sso_sub: The unique 'sub' (subject) claim from the JWT, used to
     *            uniquely identify the external SSO user.
     * - role_id: Foreign key linking the user to a local role.
     * - password: Made nullable because SSO-managed users do not have
     *             a local password.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Unique subject identifier from the central SSO JWT payload
            $table->string('sso_sub')->nullable()->unique()->after('email')
                  ->comment('Unique subject claim from the SSO JWT');

            // Foreign key to the local roles table
            $table->foreignId('role_id')->nullable()->after('sso_sub')
                  ->constrained('roles')->nullOnDelete()
                  ->comment('Assigned local role for this user');

            // SSO users won't have a local password
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['sso_sub', 'role_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
