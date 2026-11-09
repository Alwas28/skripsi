<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('exam_types')->insert([
            ['code' => 'proposal', 'name' => 'Ujian Proposal', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'result', 'name' => 'Ujian Hasil', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'thesis', 'name' => 'Ujian Skripsi', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['thesis_id', 'exam_type_id']);
        });

        // Not unique per exam: an attempt rejected at verification keeps its
        // row and the number is used again by the next request.
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_examiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'position']);
            $table->unique(['exam_attempt_id', 'lecturer_id']);
        });

        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('exam_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('exam_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->string('assessor_role');
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'assessor_role']);
        });

        Schema::create('exam_assessment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('component');
            $table->unsignedTinyInteger('weight');
            $table->decimal('score', 5, 2);
            $table->timestamps();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('final_score', 5, 2);
            $table->string('decision');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('exam_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->string('chapter');
            $table->text('notes');
            $table->string('status');
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exam_revision_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_revision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pembimbing's "layak diuji" sign-off (blueprint.pdf §7), renewed for
        // every attempt.
        Schema::create('exam_readiness_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained()->cascadeOnDelete();
            $table->string('exam_kind');
            $table->unsignedInteger('attempt_number');
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['thesis_id', 'exam_kind', 'attempt_number', 'lecturer_id'], 'exam_readiness_unique');
        });
    }

    public function down(): void
    {
        foreach ([
            'exam_readiness_approvals', 'exam_revision_validations', 'exam_revisions', 'exam_results',
            'exam_assessment_details', 'exam_assessments', 'exam_schedules', 'exam_examiners',
            'exam_attempts', 'exams', 'exam_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
