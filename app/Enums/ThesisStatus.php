<?php

namespace App\Enums;

/**
 * Full workflow status per blueprint.pdf §24 "Status Final Sistem". Only
 * DRAFT..TITLE_APPROVED are actually reachable so far (Pengajuan Judul
 * module) — the rest exist now so later modules (Bimbingan, Ujian, ...)
 * extend this same column instead of each module inventing its own enum.
 *
 * Title approval is sequential: SUBMITTED waits for the Dosen PA, then
 * TITLE_REVIEW waits for the Kaprodi.
 */
enum ThesisStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case TitleReview = 'TITLE_REVIEW';
    case RevisionRequired = 'REVISION_REQUIRED';
    case TitleApproved = 'TITLE_APPROVED';
    case SupervisorAssigned = 'SUPERVISOR_ASSIGNED';
    case Supervision = 'SUPERVISION';
    case ReadyProposalExam = 'READY_PROPOSAL_EXAM';
    case ProposalExam = 'PROPOSAL_EXAM';
    case ProposalRevision = 'PROPOSAL_REVISION';
    case ProposalFailed = 'PROPOSAL_FAILED';
    case ProposalResupervision = 'PROPOSAL_RESUPERVISION';
    case ProposalReexam = 'PROPOSAL_REEXAM';
    case ProposalPassed = 'PROPOSAL_PASSED';
    case Research = 'RESEARCH';
    case ResultSupervision = 'RESULT_SUPERVISION';
    case ReadyResultExam = 'READY_RESULT_EXAM';
    case ResultExam = 'RESULT_EXAM';
    case ResultRevision = 'RESULT_REVISION';
    case ResultFailed = 'RESULT_FAILED';
    case ResultResupervision = 'RESULT_RESUPERVISION';
    case ResultReexam = 'RESULT_REEXAM';
    case ResultPassed = 'RESULT_PASSED';
    case FinalSupervision = 'FINAL_SUPERVISION';
    case ReadyThesisExam = 'READY_THESIS_EXAM';
    case ThesisExam = 'THESIS_EXAM';
    case ThesisRevision = 'THESIS_REVISION';
    case ThesisFailed = 'THESIS_FAILED';
    case ThesisResupervision = 'THESIS_RESUPERVISION';
    case ThesisReexam = 'THESIS_REEXAM';
    case ThesisPassed = 'THESIS_PASSED';
    case FinalValidation = 'FINAL_VALIDATION';
    case Completed = 'COMPLETED';

    /**
     * The statuses this one may move to — the "no stage can be skipped" rule
     * from blueprint.pdf §1, following the flow in §3.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::TitleReview, self::RevisionRequired],
            self::TitleReview => [self::TitleApproved, self::RevisionRequired],
            self::RevisionRequired => [self::Submitted],
            self::TitleApproved => [self::SupervisorAssigned],
            self::SupervisorAssigned => [self::Supervision],
            self::Supervision => [self::ReadyProposalExam],
            self::ReadyProposalExam => [self::ProposalExam, self::Supervision],
            self::ProposalExam => [self::ProposalPassed, self::ProposalRevision, self::ProposalFailed],
            self::ProposalReexam => [self::ProposalPassed, self::ProposalRevision, self::ProposalFailed, self::ProposalResupervision],
            self::ProposalRevision => [self::ProposalPassed],
            self::ProposalFailed => [self::ProposalResupervision],
            self::ProposalResupervision => [self::ProposalReexam],
            self::ProposalPassed => [self::Research],
            self::Research => [self::ResultSupervision],
            self::ResultSupervision => [self::ReadyResultExam],
            self::ReadyResultExam => [self::ResultExam, self::ResultSupervision],
            self::ResultExam => [self::ResultPassed, self::ResultRevision, self::ResultFailed],
            self::ResultReexam => [self::ResultPassed, self::ResultRevision, self::ResultFailed, self::ResultResupervision],
            self::ResultRevision => [self::ResultPassed],
            self::ResultFailed => [self::ResultResupervision],
            self::ResultResupervision => [self::ResultReexam],
            self::ResultPassed => [self::FinalSupervision],
            self::FinalSupervision => [self::ReadyThesisExam],
            self::ReadyThesisExam => [self::ThesisExam, self::FinalSupervision],
            self::ThesisExam => [self::ThesisPassed, self::ThesisRevision, self::ThesisFailed],
            self::ThesisReexam => [self::ThesisPassed, self::ThesisRevision, self::ThesisFailed, self::ThesisResupervision],
            self::ThesisRevision => [self::ThesisPassed],
            self::ThesisFailed => [self::ThesisResupervision],
            self::ThesisResupervision => [self::ThesisReexam],
            self::ThesisPassed => [self::FinalValidation],
            self::FinalValidation => [self::Completed],
            self::Completed => [],
        };
    }

    /**
     * Whether the workflow has got as far as the given status — the cases
     * above are declared in workflow order.
     */
    public function hasReached(self $status): bool
    {
        return array_search($this, self::cases(), true) >= array_search($status, self::cases(), true);
    }

    /**
     * The milestones shown on progress views, each done once the workflow
     * reaches the given status.
     *
     * @return list<array{label: string, done: bool}>
     */
    public function milestones(): array
    {
        return array_map(fn (array $milestone) => ['label' => $milestone[0], 'done' => $this->hasReached($milestone[1])], [
            ['Judul Disetujui', self::TitleApproved],
            ['Pembimbing Ditetapkan', self::SupervisorAssigned],
            ['Bimbingan Proposal', self::ReadyProposalExam],
            ['Ujian Proposal', self::ProposalPassed],
            ['Penelitian', self::ResultSupervision],
            ['Bimbingan Hasil', self::ReadyResultExam],
            ['Ujian Hasil', self::ResultPassed],
            ['Bimbingan Akhir', self::ReadyThesisExam],
            ['Ujian Skripsi', self::ThesisPassed],
            ['Validasi Akhir', self::Completed],
        ]);
    }

    public function progressPercent(): int
    {
        $milestones = $this->milestones();

        return (int) round(count(array_filter($milestones, fn (array $milestone) => $milestone['done'])) / count($milestones) * 100);
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Menunggu Persetujuan PA',
            self::TitleReview => 'Menunggu Persetujuan Kaprodi',
            self::RevisionRequired => 'Perlu Revisi',
            self::TitleApproved => 'Judul Disetujui',
            self::SupervisorAssigned => 'Pembimbing Ditetapkan',
            self::Supervision => 'Bimbingan Proposal',
            self::ReadyProposalExam => 'Mengajukan Ujian Proposal',
            self::ProposalExam => 'Ujian Proposal',
            self::ProposalRevision => 'Revisi Ujian Proposal',
            self::ProposalFailed => 'Tidak Lulus Ujian Proposal',
            self::ProposalResupervision => 'Bimbingan Ulang Proposal',
            self::ProposalReexam => 'Ujian Ulang Proposal',
            self::ProposalPassed => 'Lulus Ujian Proposal',
            self::Research => 'Penelitian',
            self::ResultSupervision => 'Bimbingan Hasil',
            self::ReadyResultExam => 'Mengajukan Ujian Hasil',
            self::ResultExam => 'Ujian Hasil',
            self::ResultRevision => 'Revisi Ujian Hasil',
            self::ResultFailed => 'Tidak Lulus Ujian Hasil',
            self::ResultResupervision => 'Bimbingan Ulang Hasil',
            self::ResultReexam => 'Ujian Ulang Hasil',
            self::ResultPassed => 'Lulus Ujian Hasil',
            self::FinalSupervision => 'Bimbingan Akhir',
            self::ReadyThesisExam => 'Mengajukan Ujian Skripsi',
            self::ThesisExam => 'Ujian Skripsi',
            self::ThesisRevision => 'Revisi Ujian Skripsi',
            self::ThesisFailed => 'Tidak Lulus Ujian Skripsi',
            self::ThesisResupervision => 'Bimbingan Ulang Skripsi',
            self::ThesisReexam => 'Ujian Ulang Skripsi',
            self::ThesisPassed => 'Lulus Ujian Skripsi',
            self::FinalValidation => 'Validasi Akhir',
            self::Completed => 'Selesai',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-gray',
            self::Submitted, self::TitleReview => 'badge-blue',
            self::RevisionRequired => 'badge-amber',
            self::TitleApproved, self::SupervisorAssigned, self::ProposalPassed, self::ResultPassed, self::ThesisPassed, self::Completed => 'badge-green',
            self::ProposalRevision, self::ResultRevision, self::ThesisRevision => 'badge-amber',
            self::ProposalFailed, self::ResultFailed, self::ThesisFailed => 'badge-red',
            self::Supervision, self::ResultSupervision, self::FinalSupervision, self::Research,
            self::ReadyProposalExam, self::ReadyResultExam, self::ReadyThesisExam,
            self::ProposalExam, self::ResultExam, self::ThesisExam,
            self::ProposalResupervision, self::ResultResupervision, self::ThesisResupervision,
            self::ProposalReexam, self::ResultReexam, self::ThesisReexam, self::FinalValidation => 'badge-blue',
            default => 'badge-gray',
        };
    }
}
