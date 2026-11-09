<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "2025/2026"
            $table->string('semester'); // Ganjil, Genap
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Same year can have a Ganjil and a Genap record; the pair must be unique.
            $table->unique(['name', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
