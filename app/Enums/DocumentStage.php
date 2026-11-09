<?php

namespace App\Enums;

/**
 * Thesis document stages, in the order of blueprint.pdf §15.
 */
enum DocumentStage: string
{
    case TitleSubmission = 'title_submission';
    case Proposal = 'proposal';
    case Supervision = 'supervision';
    case ProposalExam = 'proposal_exam';
    case Research = 'research';
    case ResultExam = 'result_exam';
    case FinalThesis = 'final_thesis';
    case ThesisExam = 'thesis_exam';
    case Revision = 'revision';
    case Minutes = 'minutes';

    public function label(): string
    {
        return match ($this) {
            self::TitleSubmission => 'Pengajuan Judul',
            self::Proposal => 'Proposal',
            self::Supervision => 'Bimbingan',
            self::ProposalExam => 'Ujian Proposal',
            self::Research => 'Penelitian',
            self::ResultExam => 'Ujian Hasil',
            self::FinalThesis => 'Skripsi Final',
            self::ThesisExam => 'Ujian Skripsi',
            self::Revision => 'Revisi',
            self::Minutes => 'Berita Acara',
        };
    }

    /**
     * The thesis status from which students may upload to this stage. Null
     * means students never upload here directly: Berita Acara comes from the
     * exam process.
     */
    public function opensAt(): ?ThesisStatus
    {
        return match ($this) {
            self::Minutes => null,
            self::TitleSubmission => ThesisStatus::Draft,
            self::Proposal, self::Supervision => ThesisStatus::SupervisorAssigned,
            self::ProposalExam => ThesisStatus::ReadyProposalExam,
            self::Research => ThesisStatus::Research,
            self::ResultExam => ThesisStatus::ReadyResultExam,
            self::FinalThesis => ThesisStatus::FinalSupervision,
            self::ThesisExam => ThesisStatus::ReadyThesisExam,
            self::Revision => ThesisStatus::ProposalRevision,
        };
    }

    public function isOpenFor(ThesisStatus $status): bool
    {
        // Title documents freeze while the PA and Kaprodi are reviewing them.
        if ($this === self::TitleSubmission) {
            return in_array($status, [ThesisStatus::Draft, ThesisStatus::RevisionRequired], true);
        }

        $opensAt = $this->opensAt();

        return $opensAt !== null && $status !== ThesisStatus::Completed && $status->hasReached($opensAt);
    }

    /**
     * @return list<self>
     */
    public static function openFor(ThesisStatus $status): array
    {
        return array_values(array_filter(self::cases(), fn (self $stage) => $stage->isOpenFor($status)));
    }
}
