<?php

namespace App\Models;

use App\Enums\ExamDecision;
use App\Enums\ExamKind;
use App\Enums\SupervisionStatus;
use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

#[Fillable(['student_id', 'academic_year_id', 'status'])]
class Thesis extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ThesisStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function title(): HasOne
    {
        return $this->hasOne(ThesisTitle::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ThesisSupervisor::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function supervisionSessions(): HasMany
    {
        return $this->hasMany(SupervisionSession::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function readinessApprovals(): HasMany
    {
        return $this->hasMany(ExamReadinessApproval::class);
    }

    /**
     * @return Collection<int, ThesisSupervisor>
     */
    public function approvedSupervisors(): Collection
    {
        return $this->supervisors()->where('status', SupervisorApprovalStatus::Approved)->with('lecturer.user')->get();
    }

    public function isSupervisedBy(Lecturer $lecturer): bool
    {
        return $this->supervisors()
            ->where('status', SupervisorApprovalStatus::Approved)
            ->where('lecturer_id', $lecturer->id)
            ->exists();
    }

    public function examOf(ExamKind $kind): ?Exam
    {
        return $this->exams()->whereHas('type', fn (Builder $type) => $type->where('code', $kind->value))->first();
    }

    public function latestAttemptOf(ExamKind $kind): ?ExamAttempt
    {
        return $this->examOf($kind)?->attempts()->latest('id')->first();
    }

    /**
     * Only a failed exam uses up an attempt (blueprint.pdf §14).
     */
    public function nextAttemptNumber(ExamKind $kind): int
    {
        return ($this->failedAttemptsOf($kind)?->count() ?? 0) + 1;
    }

    public function canRequestExam(ExamKind $kind): bool
    {
        return in_array($this->status, [$kind->supervisionStatus(), $kind->resupervisionStatus()], true);
    }

    /**
     * The configurable requirements of blueprint.pdf §26, checked against
     * this thesis. Administrasi is confirmed by Prodi at verification.
     *
     * @return list<array{label: string, met: bool}>
     */
    public function examRequirementChecks(ExamKind $kind): array
    {
        $rules = Setting::examRules($this->student->study_program_id, $kind);
        $attemptNumber = $this->nextAttemptNumber($kind);
        $approvedSessions = $this->supervisionSessions()->where('status', SupervisionStatus::Approved);
        $checks = [];

        if ($attemptNumber > 1) {
            $failedAt = $this->failedAttemptsOf($kind)->sortByDesc('id')->first()->result->decided_at;
            $checks[] = ['label' => 'Minimal 1 bimbingan ulang disetujui', 'met' => (clone $approvedSessions)->where('approved_at', '>=', $failedAt)->exists()];
        } elseif ($rules['min_supervisions'] > 0) {
            $count = (clone $approvedSessions)->count();
            $checks[] = ['label' => "Minimal {$rules['min_supervisions']} bimbingan disetujui (saat ini {$count})", 'met' => $count >= $rules['min_supervisions']];
        }

        if ($rules['supervisor_approval']) {
            $approvals = $this->readinessApprovals()->where('exam_kind', $kind->value)->where('attempt_number', $attemptNumber)->count();
            $required = $this->approvedSupervisors()->count();
            $checks[] = ['label' => "Persetujuan pembimbing ({$approvals} dari {$required})", 'met' => $required > 0 && $approvals >= $required];
        }

        if ($rules['document_required']) {
            $stage = $kind->requiredDocumentStage();
            $hasDocument = $this->documents()
                ->where('stage', $stage->value)
                ->whereHas('versions', fn (Builder $versions) => $versions->where('is_active', true))
                ->exists();
            $checks[] = ['label' => "Dokumen {$stage->label()} sudah diunggah", 'met' => $hasDocument];
        }

        return $checks;
    }

    /**
     * @return Collection<int, ExamAttempt>|null
     */
    private function failedAttemptsOf(ExamKind $kind): ?Collection
    {
        return $this->examOf($kind)?->attempts()
            ->whereHas('result', fn (Builder $result) => $result->where('decision', ExamDecision::Failed))
            ->with('result')
            ->get();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ThesisStatusHistory::class)->latest();
    }

    /**
     * Theses a prodi-staff user may work on: those of their own study
     * program, none if the account has no study program yet, all for a super
     * admin.
     */
    #[Scope]
    protected function visibleToProdiStaff(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (! $user->study_program_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('student', fn (Builder $student) => $student->where('study_program_id', $user->study_program_id));
    }

    /**
     * Staff of this thesis's study program holding the given role.
     *
     * @return Collection<int, User>
     */
    public function prodiStaffWithRole(string $roleSlug): Collection
    {
        return User::where('study_program_id', $this->student->study_program_id)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $roleSlug))
            ->get();
    }

    public function isVisibleToProdiStaff(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->study_program_id && $this->student->study_program_id === $user->study_program_id);
    }

    /**
     * The one place status ever changes — always paired with a history row,
     * so no caller can update status without leaving an audit trail
     * (blueprint.pdf §21 Audit Trail).
     */
    public function transitionTo(ThesisStatus $status, ?User $changedBy, ?string $notes = null): void
    {
        $from = $this->status;

        if ($from && ! $from->canTransitionTo($status)) {
            throw new DomainException("Status skripsi tidak boleh berpindah dari {$from->value} ke {$status->value}.");
        }

        DB::transaction(function () use ($from, $status, $changedBy, $notes) {
            $this->update(['status' => $status]);

            $this->statusHistories()->create([
                'from_status' => $from?->value,
                'to_status' => $status->value,
                'changed_by' => $changedBy?->id,
                'notes' => $notes,
            ]);
        });
    }
}
