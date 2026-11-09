<?php

namespace App\Models;

use App\Enums\SupervisorApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_attempt_id', 'room_id', 'exam_date', 'start_time', 'end_time'])]
class ExamSchedule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function timeRange(): string
    {
        return str_replace(':', '.', substr($this->start_time, 0, 5)).'–'.str_replace(':', '.', substr($this->end_time, 0, 5));
    }

    /**
     * Double-bookings the proposed slot would cause (blueprint.pdf §11):
     * the student, the supervisors, the examiners, or the room.
     *
     * @return list<string>
     */
    public static function conflictsFor(ExamAttempt $attempt, string $date, string $start, string $end, int $roomId): array
    {
        $thesis = $attempt->exam->thesis;
        $people = [];

        foreach ($attempt->examiners()->with('lecturer')->get() as $examiner) {
            $people[$examiner->lecturer_id][] = "Penguji {$examiner->position} ({$examiner->lecturer->full_name})";
        }

        foreach ($thesis->approvedSupervisors() as $supervisor) {
            $people[$supervisor->lecturer_id][] = "Pembimbing {$supervisor->position} ({$supervisor->lecturer->full_name})";
        }

        $conflicts = [];
        $others = static::whereDate('exam_date', $date)
            ->where('exam_attempt_id', '!=', $attempt->id)
            ->with(['room', 'attempt.examiners', 'attempt.exam.thesis.supervisors'])
            ->get()
            ->filter(fn (self $other) => substr($other->start_time, 0, 5) < $end && substr($other->end_time, 0, 5) > $start);

        foreach ($others as $other) {
            $slot = "pada pukul {$other->timeRange()}";
            $otherThesis = $other->attempt->exam->thesis;

            if ($other->room_id === $roomId) {
                $conflicts[] = "Ruang {$other->room->name} sudah dipakai {$slot}.";
            }

            if ($otherThesis->student_id === $thesis->student_id) {
                $conflicts[] = "Mahasiswa sudah memiliki jadwal {$slot}.";
            }

            $busy = $other->attempt->examiners->pluck('lecturer_id')
                ->merge($otherThesis->supervisors->where('status', SupervisorApprovalStatus::Approved)->pluck('lecturer_id'))
                ->unique();

            foreach ($busy as $lecturerId) {
                foreach ($people[$lecturerId] ?? [] as $label) {
                    $conflicts[] = "{$label} sudah memiliki jadwal {$slot}.";
                }
            }
        }

        return array_values(array_unique($conflicts));
    }
}
