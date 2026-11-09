<?php

namespace App\Enums;

/**
 * The three exams, and how each maps onto the shared status pattern of
 * blueprint.pdf §24 (ready → exam → revision / failed → resupervision →
 * reexam → passed).
 */
enum ExamKind: string
{
    case Proposal = 'proposal';
    case Result = 'result';
    case Thesis = 'thesis';

    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Ujian Proposal',
            self::Result => 'Ujian Hasil',
            self::Thesis => 'Ujian Skripsi',
        };
    }

    public function supervisionLabel(): string
    {
        return match ($this) {
            self::Proposal => 'Bimbingan Proposal',
            self::Result => 'Bimbingan Hasil',
            self::Thesis => 'Bimbingan Akhir',
        };
    }

    public function viewPermission(): string
    {
        return match ($this) {
            self::Proposal => 'lihat.exam_proposal',
            self::Result => 'lihat.exam_result',
            self::Thesis => 'lihat.exam_thesis',
        };
    }

    public function supervisionStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::Supervision,
            self::Result => ThesisStatus::ResultSupervision,
            self::Thesis => ThesisStatus::FinalSupervision,
        };
    }

    public function readyStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ReadyProposalExam,
            self::Result => ThesisStatus::ReadyResultExam,
            self::Thesis => ThesisStatus::ReadyThesisExam,
        };
    }

    public function examStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalExam,
            self::Result => ThesisStatus::ResultExam,
            self::Thesis => ThesisStatus::ThesisExam,
        };
    }

    public function revisionStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalRevision,
            self::Result => ThesisStatus::ResultRevision,
            self::Thesis => ThesisStatus::ThesisRevision,
        };
    }

    public function failedStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalFailed,
            self::Result => ThesisStatus::ResultFailed,
            self::Thesis => ThesisStatus::ThesisFailed,
        };
    }

    public function resupervisionStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalResupervision,
            self::Result => ThesisStatus::ResultResupervision,
            self::Thesis => ThesisStatus::ThesisResupervision,
        };
    }

    public function reexamStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalReexam,
            self::Result => ThesisStatus::ResultReexam,
            self::Thesis => ThesisStatus::ThesisReexam,
        };
    }

    public function passedStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::ProposalPassed,
            self::Result => ThesisStatus::ResultPassed,
            self::Thesis => ThesisStatus::ThesisPassed,
        };
    }

    /**
     * Where the workflow goes straight after passing.
     */
    public function afterPassedStatus(): ThesisStatus
    {
        return match ($this) {
            self::Proposal => ThesisStatus::Research,
            self::Result => ThesisStatus::FinalSupervision,
            self::Thesis => ThesisStatus::FinalValidation,
        };
    }

    /**
     * The document stage that must hold an active file before the exam, if
     * the study program requires one.
     */
    public function requiredDocumentStage(): DocumentStage
    {
        return match ($this) {
            self::Proposal => DocumentStage::Proposal,
            self::Result => DocumentStage::Research,
            self::Thesis => DocumentStage::FinalThesis,
        };
    }

    /**
     * Statuses in which the student may hold a supervision session for this
     * exam. Failed and the pre-supervision statuses move forward on the
     * first new session.
     *
     * @return list<ThesisStatus>
     */
    public function supervisableStatuses(): array
    {
        $start = match ($this) {
            self::Proposal => [ThesisStatus::SupervisorAssigned],
            self::Result => [ThesisStatus::Research],
            self::Thesis => [],
        };

        return [...$start, $this->supervisionStatus(), $this->failedStatus(), $this->resupervisionStatus()];
    }

    public static function supervisableFor(ThesisStatus $status): ?self
    {
        foreach (self::cases() as $kind) {
            if (in_array($status, $kind->supervisableStatuses(), true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The exam a status is currently waiting on or undergoing.
     */
    public static function inProgressFor(ThesisStatus $status): ?self
    {
        foreach (self::cases() as $kind) {
            if (in_array($status, [$kind->readyStatus(), $kind->examStatus(), $kind->reexamStatus(), $kind->revisionStatus()], true)) {
                return $kind;
            }
        }

        return null;
    }
}
