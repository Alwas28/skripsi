<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // "aksi.fitur" slug, e.g. "tambah.user" — see database/seeders/RolePermissionSeeder.php
            $table->string('name')->unique();
            $table->string('action');
            $table->string('action_label');
            $table->string('feature');
            $table->string('feature_label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
