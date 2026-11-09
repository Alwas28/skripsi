<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamDecision;
use App\Enums\ExamKind;
use App\Enums\ExamRevisionStatus;
use App\Enums\SupervisorApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['exam_id', 'attempt_number', 'status', 'verified_by', 'verified_at', 'verification_notes'])]
class ExamAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ExamAttemptStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examiners(): HasMany
    {
        return $this->hasMany(ExamExaminer::class)->orderBy('position');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(ExamSchedule::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ExamAssessment::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ExamRevision::class);
    }

    public function kind(): ExamKind
    {
        return $this->exam->type->code;
    }

    public static function assessorLabel(string $role): string
    {
        [$type, $position] = explode('_', $role);

        return ($type === 'examiner' ? 'Penguji ' : 'Pembimbing ').$position;
    }

    public function minutesNumber(): string
    {
        return sprintf('BA/%s/%d/%s', strtoupper($this->kind()->value), $this->id, $this->result->decided_at->format('Y'));
    }

    /**
     * Who must grade this attempt, by assessor role (blueprint.pdf §12).
     *
     * @return array<string, int> assessor role => lecturer id
     */
    public function requiredAssessors(): array
    {
        $assessors = [];

        foreach ($this->examiners as $examiner) {
            $assessors["examiner_{$examiner->position}"] = $examiner->lecturer_id;
        }

        foreach ($this->exam->thesis->supervisors->where('status', SupervisorApprovalStatus::Approved) as $supervisor) {
            $assessors["supervisor_{$supervisor->position}"] = $supervisor->lecturer_id;
        }

        return $assessors;
    }

    /**
     * The weighted final score, or null until every assessor has graded.
     */
    public function calculatedScore(): ?float
    {
        $required = array_keys($this->requiredAssessors());
        $assessments = $this->assessments->keyBy('assessor_role');

        if ($required === [] || array_diff($required, $assessments->keys()->all()) !== []) {
            return null;
        }

        $rules = Setting::examRules($this->exam->thesis->student->study_program_id, $this->kind());
        $average = fn (string $prefix) => $assessments
            ->filter(fn (ExamAssessment $assessment, string $role) => str_starts_with($role, $prefix))
            ->avg('score') ?? 0;

        return round(
            $average('examiner_') * $rules['examiner_weight'] / 100 + $average('supervisor_') * $rules['supervisor_weight'] / 100,
            2
        );
    }

    public function passScore(): float
    {
        return (float) Setting::examRules($this->exam->thesis->student->study_program_id, $this->kind())['pass_score'];
    }

    /**
     * Record the outcome and move the thesis on (blueprint.pdf §8).
     */
    public function decide(ExamDecision $decision, float $finalScore, User $decidedBy): void
    {
        $kind = $this->kind();
        $thesis = $this->exam->thesis;

        DB::transaction(function () use ($decision, $finalScore, $decidedBy, $kind, $thesis) {
            $this->result()->create([
                'final_score' => $finalScore,
                'decision' => $decision,
                'decided_by' => $decidedBy->id,
                'decided_at' => now(),
            ]);
            $this->update(['status' => ExamAttemptStatus::Decided]);

            match ($decision) {
                ExamDecision::Passed => $this->advancePastExam($thesis, $kind, $decidedBy),
                ExamDecision::Revision => $thesis->transitionTo($kind->revisionStatus(), $decidedBy, 'Lulus dengan revisi.'),
                ExamDecision::Failed => $thesis->transitionTo($kind->failedStatus(), $decidedBy, 'Tidak lulus.'),
            };
        });
    }

    /**
     * Once no revision item is left open the exam counts as passed
     * (blueprint.pdf §13).
     */
    public function completeRevisionsIfDone(User $validatedBy): bool
    {
        if ($this->revisions()->where('status', '!=', ExamRevisionStatus::Approved)->exists()) {
            return false;
        }

        $this->advancePastExam($this->exam->thesis, $this->kind(), $validatedBy);

        return true;
    }

    private function advancePastExam(Thesis $thesis, ExamKind $kind, User $user): void
    {
        DB::transaction(function () use ($thesis, $kind, $user) {
            $thesis->transitionTo($kind->passedStatus(), $user, 'Lulus.');
            $thesis->transitionTo($kind->afterPassedStatus(), $user);
        });
    }
}
