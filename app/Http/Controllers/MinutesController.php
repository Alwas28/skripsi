<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable Berita Acara for everyone involved in the exam.
 */
class MinutesController extends Controller
{
    public function show(Request $request, ExamAttempt $examAttempt): View
    {
        abort_unless($examAttempt->status === ExamAttemptStatus::Decided, 404);
        abort_unless($this->canView($request, $examAttempt), 403);

        $examAttempt->load(['exam.thesis.student.studyProgram', 'exam.thesis.title', 'examiners.lecturer', 'schedule.room', 'assessments.lecturer', 'result']);

        return view('minutes.show', ['attempt' => $examAttempt]);
    }

    private function canView(Request $request, ExamAttempt $attempt): bool
    {
        $user = $request->user();
        $thesis = $attempt->exam->thesis;
        $lecturer = $user->lecturerRecord;

        return $thesis->student->user_id === $user->id
            || ($user->hasPermission('lihat.assessment') && $thesis->isVisibleToProdiStaff($user))
            || ($lecturer && in_array($lecturer->id, $attempt->requiredAssessors(), true));
    }
}
