<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per thesis, updated in place across revisions — the
        // per-revision audit trail lives in thesis_status_histories instead
        // of duplicating full content rows (blueprint.pdf §22).
        Schema::create('thesis_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('research_field')->nullable();
            $table->text('background')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('objectives')->nullable();
            $table->text('method')->nullable();
            $table->text('research_object')->nullable();
            $table->text('initial_references')->nullable();
            $table->string('proposal_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_titles');
    }
};
