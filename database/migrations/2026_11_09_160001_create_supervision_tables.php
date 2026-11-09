<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervision_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->string('phase');
            $table->string('chapter');
            $table->string('topic');
            $table->date('session_date');
            $table->string('status');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['thesis_id', 'status']);
        });

        Schema::create('supervision_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('supervision_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('supervision_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervision_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('is_active');
            $table->text('review_notes')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'review_notes', 'reviewed_at']);
        });

        Schema::dropIfExists('supervision_revisions');
        Schema::dropIfExists('supervision_notes');
        Schema::dropIfExists('supervision_files');
        Schema::dropIfExists('supervision_sessions');
    }
};
