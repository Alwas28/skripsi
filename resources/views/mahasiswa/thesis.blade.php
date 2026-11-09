<x-app-layout>
    <x-slot name="header">Skripsi Saya</x-slot>

    @if (! $thesis)
        <div class="card animate-fadeUp">
            <div class="card-body">
                <p style="font-size:13px;color:var(--text)">Belum ada data skripsi. Mulai dari menu <a href="{{ route('mahasiswa.title-submission') }}" class="auth-link">Pengajuan Judul</a>.</p>
            </div>
        </div>
    @else
        <div class="card animate-fadeUp">
            <div class="card-header">
                <div class="card-title">{{ $thesis->title?->title ?: 'Judul belum diisi' }}</div>
                <span class="badge {{ $thesis->status->badgeClass() }}">{{ $thesis->status->label() }}</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                @include('mahasiswa._progress', ['percent' => $thesis->status->progressPercent(), 'milestones' => $thesis->status->milestones()])
            </div>
        </div>

        <div class="card animate-fadeUp d1" style="margin-top:20px">
            @include('prodi.title-submissions._history', ['thesis' => $thesis])
        </div>
    @endif
</x-app-layout>
