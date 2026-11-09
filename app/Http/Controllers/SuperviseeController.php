<?php

namespace App\Http\Controllers;

use App\Enums\SupervisorApprovalStatus;
use App\Models\ThesisSupervisor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperviseeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $lecturer = $request->user()->lecturerRecord;

        if (! $lecturer) {
            return view('dosen.advisor-titles.not-linked', ['pageTitle' => 'Mahasiswa Bimbingan']);
        }

        return view('dosen.supervisees.index', [
            'assignments' => ThesisSupervisor::whereBelongsTo($lecturer)
                ->where('status', SupervisorApprovalStatus::Approved)
                ->with(['thesis.student.studyProgram', 'thesis.title'])
                ->latest()
                ->paginate(15),
        ]);
    }
}
