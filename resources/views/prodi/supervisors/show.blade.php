<x-app-layout>
    <x-slot name="header">Penetapan Pembimbing</x-slot>

    @php
        $supervisor1 = $thesis->supervisors->firstWhere('position', 1);
        $supervisor2 = $thesis->supervisors->firstWhere('position', 2);
    @endphp

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">
                    {{ $thesis->student->nim }} &middot; {{ $thesis->student->studyProgram->name ?? '—' }}
                    &middot; Dosen PA: {{ $thesis->student->academicAdvisor?->full_name ?? 'Belum ditentukan' }}
                </div>
                <div style="font-size:13px;color:var(--text);margin-top:8px;font-weight:600">{{ $thesis->title->title }}</div>
            </div>
            @if ($supervisor1)
                <span class="badge {{ $supervisor1->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $supervisor1->status->label() }}</span>
            @else
                <span class="badge badge-gray" style="font-size:12px;padding:5px 14px">Belum diusulkan</span>
            @endif
        </div>
    </div>

    <div class="two-col animate-fadeUp d1" style="margin-top:20px">
        <div style="display:flex;flex-direction:column;gap:20px">
            @if ($supervisor1?->review_notes)
                <div class="card" style="border-color:rgba(245,158,11,.35)">
                    <div class="card-body">
                        <div style="font-size:13px;font-weight:600;color:var(--text)">Catatan Kaprodi</div>
                        <p style="font-size:13px;color:var(--sub);margin-top:4px;white-space:pre-line">{{ $supervisor1->review_notes }}</p>
                    </div>
                </div>
            @endif

            @if ($supervisor1)
                <div class="card">
                    <div class="card-header"><div class="card-title">Usulan Pembimbing</div></div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                        <div style="font-size:13px;color:var(--text)"><strong>Pembimbing 1:</strong> {{ $supervisor1->lecturer->full_name }}</div>
                        <div style="font-size:13px;color:var(--text)"><strong>Pembimbing 2:</strong> {{ $supervisor2?->lecturer->full_name ?? '—' }}</div>
                    </div>
                </div>
            @endif

            @if ($canPropose)
                <div class="card">
                    <div class="card-header"><div class="card-title">{{ $supervisor1 ? 'Ubah Usulan' : 'Usulkan Pembimbing' }}</div></div>
                    <form method="POST" action="{{ route('prodi.supervisors.store', $thesis) }}">
                        @csrf
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
                            @foreach ([1 => $supervisor1, 2 => $supervisor2] as $position => $current)
                                <div>
                                    <x-input-label for="supervisor_{{ $position }}_id" value="Pembimbing {{ $position }}" />
                                    <select id="supervisor_{{ $position }}_id" name="supervisor_{{ $position }}_id" class="f-input" required>
                                        <option value="">— Pilih dosen —</option>
                                        @foreach ($eligibleLecturers as $lecturer)
                                            <option value="{{ $lecturer->id }}" @selected((int) old("supervisor_{$position}_id", $current?->lecturer_id) === $lecturer->id)>{{ $lecturer->full_name }} ({{ $lecturer->nidn }}) — {{ $lecturer->supervision_load }} bimbingan</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('supervisor_'.$position.'_id')" />
                                </div>
                            @endforeach
                            <p class="text-muted" style="font-size:12px">Pilihan berisi dosen aktif dari program studi mahasiswa yang akunnya memiliki role Dosen Pembimbing.</p>
                            <button type="submit" class="btn btn-primary" style="align-self:flex-start">
                                <i class="fa-solid fa-paper-plane"></i>Kirim ke Kaprodi
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canDecide)
                <div class="card">
                    <div class="card-header"><div class="card-title">Persetujuan Kaprodi</div></div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                        <form method="POST" action="{{ route('prodi.supervisors.approve', $thesis) }}" onsubmit="return confirm('Tetapkan pembimbing ini?')">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa-solid fa-check"></i>Setujui &amp; Tetapkan
                            </button>
                        </form>

                        <div class="auth-divider" style="margin:0"></div>

                        <form method="POST" action="{{ route('prodi.supervisors.reject', $thesis) }}">
                            @csrf
                            <x-input-label for="review_notes" value="Alasan Penolakan" />
                            <textarea id="review_notes" name="review_notes" rows="3" class="f-input" required>{{ old('review_notes') }}</textarea>
                            <x-input-error :messages="$errors->get('review_notes')" />
                            <button type="submit" class="btn btn-secondary btn-block" style="margin-top:10px">
                                <i class="fa-solid fa-rotate-left"></i>Tolak Usulan
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Beban Dosen Program Studi</div></div>
            <div style="overflow-x:auto">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Dosen</th>
                            <th>Bidang Keahlian</th>
                            <th>Bimbingan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lecturers as $lecturer)
                            <tr>
                                <td class="td-main">{{ $lecturer->full_name }}</td>
                                <td>{{ $lecturer->expertise_field ?: '—' }}</td>
                                <td>{{ $lecturer->supervision_load }}</td>
                                <td><span class="badge {{ $lecturer->is_active ? 'badge-green' : 'badge-gray' }}">{{ $lecturer->is_active ? 'Aktif' : 'Tidak aktif' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-muted" style="text-align:center;padding:24px">Belum ada data dosen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
