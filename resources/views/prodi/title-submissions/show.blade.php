<x-app-layout>
    <x-slot name="header">Detail Pengajuan Judul</x-slot>

    @php $title = $thesis->title; @endphp

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">
                    {{ $thesis->student->nim }} &middot; {{ $thesis->student->studyProgram->name ?? '—' }}
                    &middot; Dosen PA: {{ $thesis->student->academicAdvisor?->full_name ?? 'Belum ditentukan' }}
                </div>
            </div>
            <span class="badge {{ $thesis->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $thesis->status->label() }}</span>
        </div>
    </div>

    <div class="two-col animate-fadeUp d1" style="margin-top:20px">
        <!-- Content -->
        @include('prodi.title-submissions._details', ['title' => $title, 'downloadUrl' => route('prodi.title-submissions.download', $thesis)])

        <!-- Actions + history -->
        <div style="display:flex;flex-direction:column;gap:20px">
            @if ($reviewable && Auth::user()->hasPermission('edit.title_submission'))
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Persetujuan Kaprodi</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                        <form method="POST" action="{{ route('prodi.title-submissions.approve', $thesis) }}" onsubmit="return confirm('Setujui judul ini?')">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa-solid fa-check"></i>Setujui
                            </button>
                        </form>

                        <div class="auth-divider" style="margin:0"></div>

                        <form method="POST" action="{{ route('prodi.title-submissions.request-revision', $thesis) }}">
                            @csrf
                            <x-input-label for="review_notes" value="Catatan Revisi" />
                            <textarea id="review_notes" name="review_notes" rows="4" class="f-input" placeholder="Jelaskan apa yang perlu diperbaiki mahasiswa..." required>{{ old('review_notes') }}</textarea>
                            <x-input-error :messages="$errors->get('review_notes')" />
                            <button type="submit" class="btn btn-secondary btn-block" style="margin-top:10px">
                                <i class="fa-solid fa-rotate-left"></i>Minta Revisi
                            </button>
                        </form>
                    </div>
                </div>
            @elseif ($title->review_notes)
                <div class="card">
                    <div class="card-header"><div class="card-title">Catatan Terakhir</div></div>
                    <div class="card-body">
                        <p style="font-size:13px;color:var(--sub);white-space:pre-line">{{ $title->review_notes }}</p>
                    </div>
                </div>
            @endif

            @include('prodi.title-submissions._history', ['thesis' => $thesis])
        </div>
    </div>
</x-app-layout>
