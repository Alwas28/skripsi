<?php

namespace App\Http\Controllers;

use App\Enums\ExamKind;
use App\Models\Setting;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Illuminate\View\View;

/**
 * Pengaturan → Persyaratan Ujian (blueprint.pdf §26), per study program.
 */
class ExamSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $programs = $this->manageablePrograms($request->user());
        $studyProgram = $programs->firstWhere('id', (int) $request->query('program')) ?? $programs->first();

        return view('prodi.settings.index', [
            'programs' => $programs,
            'studyProgram' => $studyProgram,
            'rules' => $studyProgram
                ? collect(ExamKind::cases())->mapWithKeys(fn (ExamKind $kind) => [$kind->value => Setting::examRules($studyProgram->id, $kind)])
                : collect(),
            'monitoring' => $studyProgram ? Setting::monitoringRules($studyProgram->id) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $programs = $this->manageablePrograms($request->user());

        $rules = [
            'study_program_id' => ['required', Rule::in($programs->pluck('id'))],
            'no_supervision_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];

        foreach (ExamKind::cases() as $kind) {
            $rules["exam.{$kind->value}.min_supervisions"] = ['required', 'integer', 'min:0', 'max:100'];
            $rules["exam.{$kind->value}.pass_score"] = ['required', 'numeric', 'min:0', 'max:100'];
            $rules["exam.{$kind->value}.examiner_weight"] = ['required', 'integer', 'min:0', 'max:100'];
            $rules["exam.{$kind->value}.supervisor_weight"] = ['required', 'integer', 'min:0', 'max:100'];
            $rules["exam.{$kind->value}.components"] = ['required', 'string'];
        }

        $data = Validator::make($request->all(), $rules)
            ->after(function (ValidatorInstance $validator) use ($request) {
                foreach (ExamKind::cases() as $kind) {
                    $input = $request->input("exam.{$kind->value}");

                    if ((int) ($input['examiner_weight'] ?? 0) + (int) ($input['supervisor_weight'] ?? 0) !== 100) {
                        $validator->errors()->add("exam.{$kind->value}.examiner_weight", 'Bobot penguji dan pembimbing harus berjumlah 100.');
                    }

                    if (Setting::parseComponents((string) ($input['components'] ?? '')) === null) {
                        $validator->errors()->add("exam.{$kind->value}.components", 'Tulis satu komponen per baris (Nama:bobot), dengan total bobot 100.');
                    }
                }
            })
            ->validate();

        foreach (ExamKind::cases() as $kind) {
            $input = $data['exam'][$kind->value];

            Setting::store($data['study_program_id'], "exam.{$kind->value}", [
                'min_supervisions' => (int) $input['min_supervisions'],
                'supervisor_approval' => $request->boolean("exam.{$kind->value}.supervisor_approval"),
                'document_required' => $request->boolean("exam.{$kind->value}.document_required"),
                'administration_required' => $request->boolean("exam.{$kind->value}.administration_required"),
                'pass_score' => (float) $input['pass_score'],
                'examiner_weight' => (int) $input['examiner_weight'],
                'supervisor_weight' => (int) $input['supervisor_weight'],
                'components' => Setting::parseComponents($input['components']),
            ]);
        }

        Setting::store($data['study_program_id'], 'monitoring', [
            'no_supervision_days' => (int) $data['no_supervision_days'],
            'max_duration_days' => (int) $data['max_duration_days'],
        ]);

        return Redirect::route('prodi.settings', ['program' => $data['study_program_id']])->with('status', 'Pengaturan berhasil disimpan.');
    }

    /**
     * @return Collection<int, StudyProgram>
     */
    private function manageablePrograms(User $user): Collection
    {
        return StudyProgram::when(! $user->isSuperAdmin(), fn ($query) => $query->whereKey($user->study_program_id ?? 0))
            ->orderBy('name')
            ->get()
            ->toBase();
    }
}
