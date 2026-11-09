<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')
                ->constrained()->nullOnDelete();
        });

        // The old free-text `role` column (added in
        // 2026_08_28_023423_add_role_to_users_table) is superseded by
        // role_id now that roles are a real, admin-manageable table.
        // Data is backfilled by database/seeders/RolePermissionSeeder.php
        // before the column is dropped in the next migration.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
