<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // Which sidebar menu group (config/menu.php) this role sees.
            // Null means: no module menu, just whatever permission-gated
            // items apply (see layouts/app.blade.php).
            $table->string('menu_group')->nullable();
            // Bypasses every permission check (App\Models\Role::hasPermission).
            // Exists so the RBAC screens themselves can never be locked out.
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
