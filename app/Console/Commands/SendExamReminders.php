<?php

namespace App\Console\Commands;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamSchedule;
use App\Notifications\ThesisUpdate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('exams:send-reminders')]
#[Description('Ingatkan mahasiswa, penguji, dan pembimbing tentang ujian besok')]
class SendExamReminders extends Command
{
    public function handle(): int
    {
        $schedules = ExamSchedule::whereDate('exam_date', today()->addDay())
            ->whereHas('attempt', fn (Builder $attempt) => $attempt->where('status', ExamAttemptStatus::Scheduled))
            ->with(['room', 'attempt.exam.type', 'attempt.exam.thesis.student.user', 'attempt.examiners.lecturer.user'])
            ->get();

        foreach ($schedules as $schedule) {
            $attempt = $schedule->attempt;
            $thesis = $attempt->exam->thesis;
            $message = "{$attempt->kind()->label()} {$thesis->student->name} besok pukul {$schedule->timeRange()}, {$schedule->room->name}.";

            ThesisUpdate::send($thesis->student->user, 'Pengingat ujian', $message, route('mahasiswa.schedule', absolute: false));
            ThesisUpdate::send(
                $attempt->examiners->map->lecturer->merge($thesis->approvedSupervisors()->map->lecturer)->map->user,
                'Pengingat ujian',
                $message,
                route('dosen.exam-schedule', absolute: false),
            );
        }

        $this->info("Pengingat dikirim untuk {$schedules->count()} ujian.");

        return self::SUCCESS;
    }
}
