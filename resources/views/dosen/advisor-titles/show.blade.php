<x-app-layout>
    <x-slot name="header">Detail Usulan Judul</x-slot>

    @php $title = $thesis->title; @endphp

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">
                    {{ $thesis->student->nim }} &middot; {{ $thesis->student->studyProgram->name ?? '—' }}
                </div>
            </div>
            <span class="badge {{ $thesis->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $thesis->status->label() }}</span>
        </div>
    </div>

    <div class="two-col animate-fadeUp d1" style="margin-top:20px">
        @include('prodi.title-submissions._details', ['title' => $title, 'downloadUrl' => route('dosen.advisor-titles.download', $thesis)])

        <div style="display:flex;flex-direction:column;gap:20px">
            @if ($reviewable)
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Persetujuan Dosen PA</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                        <form method="POST" action="{{ route('dosen.advisor-titles.approve', $thesis) }}" onsubmit="return confirm('Setujui judul ini dan teruskan ke Kaprodi?')">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fa-solid fa-check"></i>Setujui &amp; Teruskan ke Kaprodi
                            </button>
                        </form>

                        <div class="auth-divider" style="margin:0"></div>

                        <form method="POST" action="{{ route('dosen.advisor-titles.request-revision', $thesis) }}">
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
