<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();
            // restrict, not cascade: deleting a faculty must not silently wipe
            // its study programs — FacultyController::destroy() guards this
            // in the app layer too, this is just the DB-level backstop.
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('degree_level')->nullable(); // D3, S1, S2, S3
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_programs');
    }
};
