<x-app-layout>
    <x-slot name="header">Pengaturan Persyaratan Ujian</x-slot>

    @include('prodi._missing-study-program')

    @if ($studyProgram)
        @php $canEdit = Auth::user()->hasPermission('edit.setting'); @endphp

        @if ($programs->count() > 1)
            <form method="GET" action="{{ route('prodi.settings') }}" class="card animate-fadeUp" style="margin-bottom:20px">
                <div class="card-body" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                        <x-input-label for="program" value="Program Studi" />
                        <select id="program" name="program" class="f-input">
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected($program->id === $studyProgram->id)>{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">Tampilkan</button>
                </div>
            </form>
        @endif

        <form method="POST" action="{{ route('prodi.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="study_program_id" value="{{ $studyProgram->id }}">

            <fieldset @disabled(! $canEdit) style="border:none;padding:0;margin:0;display:flex;flex-direction:column;gap:20px">
                @foreach (\App\Enums\ExamKind::cases() as $kind)
                    @php
                        $rule = $rules[$kind->value];
                        $field = fn (string $name) => "exam[{$kind->value}][{$name}]";
                        $oldKey = fn (string $name) => "exam.{$kind->value}.{$name}";
                        $componentLines = collect($rule['components'])->map(fn ($c) => $c['name'].':'.$c['weight'])->implode("\n");
                    @endphp
                    <div class="card animate-fadeUp">
                        <div class="card-header"><div class="card-title">{{ $kind->label() }}</div></div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
                                <div>
                                    <x-input-label for="{{ $kind->value }}-min" value="Minimal Bimbingan" />
                                    <x-text-input id="{{ $kind->value }}-min" :name="$field('min_supervisions')" type="number" min="0" :value="old($oldKey('min_supervisions'), $rule['min_supervisions'])" required />
                                    <x-input-error :messages="$errors->get($oldKey('min_supervisions'))" />
                                </div>
                                <div>
                                    <x-input-label for="{{ $kind->value }}-pass" value="Nilai Minimal Lulus" />
                                    <x-text-input id="{{ $kind->value }}-pass" :name="$field('pass_score')" type="number" step="0.01" min="0" max="100" :value="old($oldKey('pass_score'), $rule['pass_score'])" required />
                                    <x-input-error :messages="$errors->get($oldKey('pass_score'))" />
                                </div>
                                <div>
                                    <x-input-label for="{{ $kind->value }}-eweight" value="Bobot Penguji (%)" />
                                    <x-text-input id="{{ $kind->value }}-eweight" :name="$field('examiner_weight')" type="number" min="0" max="100" :value="old($oldKey('examiner_weight'), $rule['examiner_weight'])" required />
                                    <x-input-error :messages="$errors->get($oldKey('examiner_weight'))" />
                                </div>
                                <div>
                                    <x-input-label for="{{ $kind->value }}-sweight" value="Bobot Pembimbing (%)" />
                                    <x-text-input id="{{ $kind->value }}-sweight" :name="$field('supervisor_weight')" type="number" min="0" max="100" :value="old($oldKey('supervisor_weight'), $rule['supervisor_weight'])" required />
                                    <x-input-error :messages="$errors->get($oldKey('supervisor_weight'))" />
                                </div>
                            </div>

                            <div style="display:flex;gap:20px;flex-wrap:wrap">
                                @foreach (['supervisor_approval' => 'Persetujuan pembimbing wajib', 'document_required' => 'Dokumen '.$kind->requiredDocumentStage()->label().' wajib', 'administration_required' => 'Administrasi akademik wajib'] as $name => $label)
                                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text)">
                                        <input type="checkbox" name="{{ $field($name) }}" value="1" @checked(old($oldKey($name), $rule[$name]))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>

                            <div>
                                <x-input-label for="{{ $kind->value }}-components" value="Komponen Nilai Penguji (satu per baris, Nama:bobot, total 100)" />
                                <textarea id="{{ $kind->value }}-components" name="{{ $field('components') }}" rows="4" class="f-input" required>{{ old($oldKey('components'), $componentLines) }}</textarea>
                                <x-input-error :messages="$errors->get($oldKey('components'))" />
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="card animate-fadeUp">
                    <div class="card-header"><div class="card-title">Batas Monitoring</div></div>
                    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
                        <div>
                            <x-input-label for="no_supervision_days" value="Peringatan tidak bimbingan setelah (hari)" />
                            <x-text-input id="no_supervision_days" name="no_supervision_days" type="number" min="1" :value="old('no_supervision_days', $monitoring['no_supervision_days'])" required />
                            <x-input-error :messages="$errors->get('no_supervision_days')" />
                        </div>
                        <div>
                            <x-input-label for="max_duration_days" value="Batas waktu pengerjaan (hari)" />
                            <x-text-input id="max_duration_days" name="max_duration_days" type="number" min="1" :value="old('max_duration_days', $monitoring['max_duration_days'])" required />
                            <x-input-error :messages="$errors->get('max_duration_days')" />
                        </div>
                    </div>
                </div>
            </fieldset>

            @if ($canEdit)
                <div style="margin-top:20px">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i>Simpan Pengaturan</button>
                </div>
            @endif
        </form>
    @endif
</x-app-layout>
