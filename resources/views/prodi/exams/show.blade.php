<x-app-layout>
    <x-slot name="header">{{ $kind->label() }} #{{ $attempt->attempt_number }}</x-slot>

    @php
        $user = Auth::user();
        $status = $attempt->status;
        $statusEnum = \App\Enums\ExamAttemptStatus::class;
        $examiner1 = $attempt->examiners->firstWhere('position', 1);
        $examiner2 = $attempt->examiners->firstWhere('position', 2);
    @endphp

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $thesis->student->nim }} &middot; {{ $thesis->student->studyProgram->name ?? '—' }} &middot; {{ $thesis->status->label() }}</div>
                <div style="font-size:13px;color:var(--text);margin-top:8px;font-weight:600">{{ $thesis->title?->title }}</div>
            </div>
            <span class="badge {{ $status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $status->label() }}</span>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px;margin-top:20px">
        @if ($status === $statusEnum::Requested && $user->hasPermission('edit.exam_request'))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Verifikasi Syarat</div></div>
                <form method="POST" action="{{ route('prodi.exams.verify', $attempt) }}">
                    @csrf
                    <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                        @if ($rules['administration_required'])
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text)">
                                <input type="checkbox" name="administration_ok" value="1"> Administrasi akademik terpenuhi
                            </label>
                            <x-input-error :messages="$errors->get('administration_ok')" />
                        @endif
                        <div>
                            <x-input-label for="notes" value="Catatan (wajib jika ditolak)" />
                            <textarea id="notes" name="notes" rows="3" class="f-input">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" />
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="submit" name="action" value="approve" class="btn btn-primary"><i class="fa-solid fa-check"></i>Verifikasi</button>
                            <button type="submit" name="action" value="reject" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i>Tolak</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        @if ($status !== $statusEnum::Requested && $status !== $statusEnum::Rejected)
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Penguji</div></div>
                @if ($status === $statusEnum::Verified && $user->hasPermission('edit.examiner'))
                    <form method="POST" action="{{ route('prodi.exams.examiners', $attempt) }}">
                        @csrf
                        <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;align-items:end">
                            @foreach ([1 => $examiner1, 2 => $examiner2] as $position => $current)
                                <div>
                                    <x-input-label for="examiner_{{ $position }}_id" value="Penguji {{ $position }}" />
                                    <select id="examiner_{{ $position }}_id" name="examiner_{{ $position }}_id" class="f-input" required>
                                        <option value="">— Pilih dosen —</option>
                                        @foreach ($examinerOptions as $lecturer)
                                            <option value="{{ $lecturer->id }}" @selected((int) old("examiner_{$position}_id", $current?->lecturer_id) === $lecturer->id)>{{ $lecturer->full_name }} ({{ $lecturer->nidn }})</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('examiner_'.$position.'_id')" />
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-primary" style="justify-self:start"><i class="fa-solid fa-check"></i>Simpan Penguji</button>
                        </div>
                    </form>
                @else
                    <div class="card-body" style="font-size:13px;color:var(--text);display:flex;flex-direction:column;gap:6px">
                        <div>Penguji 1: {{ $examiner1?->lecturer->full_name ?? '—' }}</div>
                        <div>Penguji 2: {{ $examiner2?->lecturer->full_name ?? '—' }}</div>
                    </div>
                @endif
            </div>

            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Jadwal</div></div>
                @if (in_array($status, [$statusEnum::Verified, $statusEnum::Scheduled], true) && $attempt->assessments->isEmpty() && $attempt->examiners->count() === 2 && $user->hasPermission('edit.schedule'))
                    <form method="POST" action="{{ route('prodi.exams.schedule', $attempt) }}">
                        @csrf
                        <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                            @foreach ($errors->get('schedule') as $conflict)
                                <div style="font-size:13px;color:#f87171"><i class="fa-solid fa-triangle-exclamation"></i> {{ $conflict }}</div>
                            @endforeach
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px">
                                <div>
                                    <x-input-label for="room_id" value="Ruang" />
                                    <select id="room_id" name="room_id" class="f-input" required>
                                        <option value="">— Pilih ruang —</option>
                                        @foreach ($rooms as $room)
                                            <option value="{{ $room->id }}" @selected((int) old('room_id', $attempt->schedule?->room_id) === $room->id)>{{ $room->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('room_id')" />
                                </div>
                                <div>
                                    <x-input-label for="exam_date" value="Tanggal" />
                                    <x-text-input id="exam_date" name="exam_date" type="date" :value="old('exam_date', $attempt->schedule?->exam_date?->toDateString())" required />
                                    <x-input-error :messages="$errors->get('exam_date')" />
                                </div>
                                <div>
                                    <x-input-label for="start_time" value="Mulai" />
                                    <x-text-input id="start_time" name="start_time" type="time" :value="old('start_time', $attempt->schedule ? substr($attempt->schedule->start_time, 0, 5) : '')" required />
                                    <x-input-error :messages="$errors->get('start_time')" />
                                </div>
                                <div>
                                    <x-input-label for="end_time" value="Selesai" />
                                    <x-text-input id="end_time" name="end_time" type="time" :value="old('end_time', $attempt->schedule ? substr($attempt->schedule->end_time, 0, 5) : '')" required />
                                    <x-input-error :messages="$errors->get('end_time')" />
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="align-self:flex-start"><i class="fa-solid fa-calendar-check"></i>Simpan Jadwal</button>
                        </div>
                    </form>
                @else
                    <div class="card-body" style="font-size:13px;color:var(--text)">
                        @if ($attempt->schedule)
                            {{ $attempt->schedule->exam_date->format('d M Y') }}, pukul {{ $attempt->schedule->timeRange() }}, {{ $attempt->schedule->room->name }}
                        @else
                            <span class="text-muted">Tetapkan kedua penguji sebelum menjadwalkan.</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @if (in_array($status, [$statusEnum::Scheduled, $statusEnum::Decided], true))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Rekap Penilaian</div></div>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead><tr><th>Penilai</th><th>Dosen</th><th>Nilai</th><th>Catatan</th></tr></thead>
                        <tbody>
                            @foreach (array_keys($assessors) as $role)
                                @php $assessment = $attempt->assessments->firstWhere('assessor_role', $role); @endphp
                                <tr>
                                    <td>{{ \App\Models\ExamAttempt::assessorLabel($role) }}</td>
                                    <td>{{ $assessment?->lecturer->full_name ?? '—' }}</td>
                                    <td>{{ $assessment ? number_format($assessment->score, 2) : 'Belum dinilai' }}</td>
                                    <td>{{ $assessment?->notes ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($attempt->revisions->isNotEmpty())
                    <div class="card-body" style="border-top:1px solid var(--border);font-size:13px;color:var(--text);display:flex;flex-direction:column;gap:6px">
                        <strong>Catatan Revisi</strong>
                        @foreach ($attempt->revisions as $revision)
                            <div>{{ $revision->chapter }} ({{ $revision->lecturer->full_name }}): {{ $revision->notes }} <span class="badge {{ $revision->status->badgeClass() }}">{{ $revision->status->label() }}</span></div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($status === $statusEnum::Scheduled && $user->hasPermission('edit.exam_decision'))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Keputusan Ujian</div></div>
                <div class="card-body" style="font-size:13px;color:var(--text)">
                    @if ($score === null)
                        <span class="text-muted">Keputusan bisa diambil setelah semua penguji dan pembimbing menilai.</span>
                    @else
                        <p style="margin-bottom:12px">Nilai akhir: <strong>{{ number_format($score, 2) }}</strong> (nilai minimal lulus {{ $rules['pass_score'] }})</p>
                        <form method="POST" action="{{ route('prodi.exams.decide', $attempt) }}" style="display:flex;gap:8px;flex-wrap:wrap">
                            @csrf
                            @foreach ($decisions as $decision)
                                <button type="submit" name="decision" value="{{ $decision->value }}" class="btn btn-primary" onclick="return confirm('Tetapkan {{ $decision->label() }}?')">{{ $decision->label() }}</button>
                            @endforeach
                        </form>
                        <x-input-error :messages="$errors->get('decision')" />
                    @endif
                </div>
            </div>
        @endif

        @if ($attempt->result)
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Hasil Ujian</div></div>
                <div class="card-body" style="font-size:13px;color:var(--text);display:flex;flex-direction:column;gap:8px">
                    <div>Nilai akhir: <strong>{{ number_format($attempt->result->final_score, 2) }}</strong> <span class="badge {{ $attempt->result->decision->badgeClass() }}">{{ $attempt->result->decision->label() }}</span></div>
                    <div><a href="{{ route('minutes.show', $attempt) }}" class="auth-link">Lihat Berita Acara</a></div>
                </div>
            </div>
        @endif

        @if ($thesis->status === \App\Enums\ThesisStatus::FinalValidation && $user->hasPermission('edit.exam_decision'))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Validasi Akhir</div></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('prodi.exams.final-validation', $thesis) }}" onsubmit="return confirm('Nyatakan skripsi ini selesai?')">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-flag-checkered"></i>Nyatakan Skripsi Selesai</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
