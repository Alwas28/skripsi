<x-app-layout>
    <x-slot name="header">{{ $kind->label() }}</x-slot>

    @if (! $thesis)
        <div class="card animate-fadeUp">
            <div class="card-body"><p class="text-muted" style="font-size:13px">Belum ada data skripsi. Mulai dari menu Pengajuan Judul.</p></div>
        </div>
    @else
        @if ($canRequest)
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Persyaratan {{ $kind->label() }}</div></div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                    @forelse ($checks as $check)
                        <div><i class="fa-solid {{ $check['met'] ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="color:{{ $check['met'] ? '#34d399' : '#f87171' }};margin-right:6px"></i>{{ $check['label'] }}</div>
                    @empty
                        <p class="text-muted">Tidak ada persyaratan khusus.</p>
                    @endforelse
                    @if (Auth::user()->hasPermission('tambah.exam_request'))
                        <form method="POST" action="{{ route('mahasiswa.exams.request', $kind) }}" onsubmit="return confirm('Ajukan {{ $kind->label() }}?')">
                            @csrf
                            <button type="submit" class="btn btn-primary" @disabled(! $allMet)><i class="fa-solid fa-paper-plane"></i>Ajukan {{ $kind->label() }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @forelse ($attempts as $attempt)
            <div class="card animate-fadeUp d1" style="margin-top:20px">
                <div class="card-header">
                    <div class="card-title">{{ $kind->label() }} #{{ $attempt->attempt_number }}</div>
                    <span class="badge {{ $attempt->status->badgeClass() }}">{{ $attempt->status->label() }}</span>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--text)">
                    @if ($attempt->verification_notes)
                        <div>Catatan Prodi: {{ $attempt->verification_notes }}</div>
                    @endif
                    @foreach ($attempt->examiners as $examiner)
                        <div>Penguji {{ $examiner->position }}: {{ $examiner->lecturer->full_name }}</div>
                    @endforeach
                    @if ($attempt->schedule)
                        <div>Jadwal: {{ $attempt->schedule->exam_date->format('d M Y') }}, pukul {{ $attempt->schedule->timeRange() }}, {{ $attempt->schedule->room->name }}</div>
                    @endif
                    @if ($attempt->result)
                        <div>Nilai: <strong>{{ number_format($attempt->result->final_score, 2) }}</strong> <span class="badge {{ $attempt->result->decision->badgeClass() }}">{{ $attempt->result->decision->label() }}</span></div>
                        <div><a href="{{ route('minutes.show', $attempt) }}" class="auth-link">Lihat Berita Acara</a></div>
                    @endif
                </div>
            </div>
        @empty
            @unless ($canRequest)
                <div class="card animate-fadeUp">
                    <div class="card-body"><p class="text-muted" style="font-size:13px">{{ $kind->label() }} belum tersedia pada tahap skripsi Anda.</p></div>
                </div>
            @endunless
        @endforelse
    @endif
</x-app-layout>
