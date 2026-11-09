<?php

namespace Tests\Concerns;

use App\Enums\SupervisionStatus;
use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;

/**
 * Shared cast for tests of the workflow after the supervisors are assigned.
 */
trait BuildsThesisWorkflow
{
    protected StudyProgram $studyProgram;

    protected User $studentUser;

    protected Lecturer $supervisorOne;

    protected Lecturer $supervisorTwo;

    protected Lecturer $examinerOne;

    protected Lecturer $examinerTwo;

    protected User $adminProdi;

    protected User $kaprodi;

    protected Room $room;

    protected function buildCast(): void
    {
        $this->studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $this->supervisorOne = $this->makeLecturer('0011', 'Ani Pembimbing', 'dosen_pembimbing');
        $this->supervisorTwo = $this->makeLecturer('0012', 'Bima Pembimbing', 'dosen_pembimbing');
        $this->examinerOne = $this->makeLecturer('0021', 'Citra Penguji', 'dosen_penguji');
        $this->examinerTwo = $this->makeLecturer('0022', 'Dewi Penguji', 'dosen_penguji');
        $this->studentUser = $this->makeStudent('2201', 'Budi Mahasiswa');
        $this->adminProdi = User::factory()->withRoles('admin_prodi')->create(['study_program_id' => $this->studyProgram->id]);
        $this->kaprodi = User::factory()->withRoles('kaprodi')->create(['study_program_id' => $this->studyProgram->id]);
        $this->room = Room::create(['name' => 'Ruang Sidang', 'code' => 'RS-1', 'is_active' => true]);
    }

    protected function makeLecturer(string $nidn, string $name, string $roleSlug): Lecturer
    {
        return Lecturer::create([
            'user_id' => User::factory()->withRoles($roleSlug)->create()->id,
            'study_program_id' => $this->studyProgram->id,
            'nidn' => $nidn, 'name' => $name, 'is_active' => true,
        ]);
    }

    protected function makeStudent(string $nim, string $name): User
    {
        $user = User::factory()->withRoles('mahasiswa')->create();

        Student::create([
            'user_id' => $user->id, 'study_program_id' => $this->studyProgram->id,
            'nim' => $nim, 'name' => $name, 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $user;
    }

    protected function thesisAt(ThesisStatus $status, ?User $student = null): Thesis
    {
        $thesis = ($student ?? $this->studentUser)->studentRecord->thesis()->create(['status' => $status]);
        $thesis->title()->create(['title' => 'Sistem Monitoring Skripsi']);

        foreach ([1 => $this->supervisorOne, 2 => $this->supervisorTwo] as $position => $lecturer) {
            $thesis->supervisors()->create([
                'lecturer_id' => $lecturer->id,
                'position' => $position,
                'status' => SupervisorApprovalStatus::Approved,
            ]);
        }

        return $thesis;
    }

    protected function approvedSessions(Thesis $thesis, int $count, string $phase = 'proposal'): void
    {
        foreach (range(1, $count) as $number) {
            $thesis->supervisionSessions()->create([
                'lecturer_id' => $this->supervisorOne->id,
                'phase' => $phase,
                'chapter' => "Bab {$number}",
                'topic' => 'Topik',
                'session_date' => now()->toDateString(),
                'status' => SupervisionStatus::Approved,
                'approved_at' => now(),
            ]);
        }
    }
}
