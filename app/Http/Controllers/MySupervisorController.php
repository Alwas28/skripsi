<?php

namespace App\Http\Controllers;

use App\Enums\SupervisorApprovalStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mahasiswa's view of their supervisors — only once the Kaprodi has approved
 * them, so a proposal still under discussion is never shown.
 */
class MySupervisorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = $request->user()->studentRecord;
        $thesis = $student?->thesis()->with('supervisors.lecturer')->first();

        return view('mahasiswa.supervisor', [
            'isLinked' => (bool) $student,
            'supervisors' => $thesis?->supervisors->where('status', SupervisorApprovalStatus::Approved) ?? collect(),
        ]);
    }
}
