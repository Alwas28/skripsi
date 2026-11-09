<x-app-layout>
    <x-slot name="header">Penilaian Ujian</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body">
            <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $attempt->exam->thesis->student->name }} &middot; {{ $attempt->kind()->label() }} #{{ $attempt->attempt_number }}</div>
            <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $attempt->exam->thesis->title?->title }}</div>
            @if ($attempt->schedule)
                <div style="font-size:13px;color:var(--text);margin-top:8px">{{ $attempt->schedule->exam_date->format('d M Y') }}, pukul {{ $attempt->schedule->timeRange() }}, {{ $attempt->schedule->room->name }}</div>
            @endif
        </div>
    </div>

    @unless ($isOpen)
        <div class="card animate-fadeUp d1" style="margin-top:20px">
            <div class="card-body"><p class="text-muted" style="font-size:13px">Penilaian dibuka pada hari ujian dan ditutup setelah keputusan ujian.</p></div>
        </div>
    @endunless

    @foreach ($roles as $role)
        @php
            $assessment = $attempt->assessments->firstWhere('assessor_role', $role);
            $isExaminer = str_starts_with($role, 'examiner_');
        @endphp
        <div class="card animate-fadeUp d1" style="margin-top:20px">
            <div class="card-header"><div class="card-title">Form {{ \App\Models\ExamAttempt::assessorLabel($role) }}</div></div>
            <form method="POST" action="{{ route('dosen.assessment.store', $attempt) }}">
                @csrf
                <input type="hidden" name="assessor_role" value="{{ $role }}">
                <fieldset @disabled(! $isOpen) style="border:none;padding:0;margin:0">
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                        @if ($isExaminer)
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
                                @foreach ($components as $index => $scoreComponent)
                                    <div>
                                        <x-input-label for="{{ $role }}-{{ $index }}" value="{{ $scoreComponent['name'] }} ({{ $scoreComponent['weight'] }}%)" />
                                        <x-text-input id="{{ $role }}-{{ $index }}" name="scores[{{ $index }}]" type="number" step="0.01" min="0" max="100" :value="old('scores.'.$index, $assessment?->details->firstWhere('component', $scoreComponent['name'])?->score)" required />
                                    </div>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('scores')" />
                        @else
                            <div style="max-width:220px">
                                <x-input-label for="{{ $role }}-score" value="Nilai" />
                                <x-text-input id="{{ $role }}-score" name="score" type="number" step="0.01" min="0" max="100" :value="old('score', $assessment?->score)" required />
                                <x-input-error :messages="$errors->get('score')" />
                            </div>
                        @endif

                        <div>
                            <x-input-label for="{{ $role }}-notes" value="Catatan" />
                            <textarea id="{{ $role }}-notes" name="notes" rows="3" class="f-input">{{ old('notes', $assessment?->notes) }}</textarea>
                        </div>

                        @if ($isExaminer)
                            <div>
                                <x-input-label for="{{ $role }}-revisions" value="Item Revisi (satu per baris, format Bab: catatan)" />
                                <textarea id="{{ $role }}-revisions" name="revisions" rows="4" class="f-input" placeholder="Bab II: Perbaiki landasan teori">{{ old('revisions', $revisionLines) }}</textarea>
                                <x-input-error :messages="$errors->get('revisions')" />
                            </div>
                        @endif

                        @if ($isOpen && Auth::user()->hasPermission('edit.assessment'))
                            <button type="submit" class="btn btn-primary" style="align-self:flex-start"><i class="fa-solid fa-check"></i>Simpan Nilai</button>
                        @endif
                        @if ($assessment)
                            <div class="text-muted" style="font-size:12px">Nilai tersimpan: {{ number_format($assessment->score, 2) }}</div>
                        @endif
                    </div>
                </fieldset>
            </form>
        </div>
    @endforeach
</x-app-layout>
