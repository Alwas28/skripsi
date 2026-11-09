<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            // Nullable: a student master record can exist before a login
            // account is created for them, or vice versa.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('entry_year', 4);
            $table->string('status')->default('aktif'); // aktif, cuti, nonaktif, lulus
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
