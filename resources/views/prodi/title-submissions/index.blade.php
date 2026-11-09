<x-app-layout>
    <x-slot name="header">Pengajuan Judul</x-slot>

    @include('prodi._missing-study-program')

    <div class="stat-card animate-fadeUp" style="max-width:260px">
        <div class="stat-icon a-bg-lt a-text"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <div class="stat-num">{{ $pendingCount }}</div>
            <div class="stat-label">Menunggu Persetujuan Kaprodi</div>
        </div>
    </div>

    <div class="card animate-fadeUp d1" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title">Daftar Pengajuan Judul</div>
            <div style="display:flex;gap:8px">
                <a href="{{ route('prodi.title-submissions.index') }}" class="btn {{ $statusFilter ? 'btn-secondary' : 'btn-primary' }}" style="padding:6px 12px;font-size:12px">Semua</a>
                <a href="{{ route('prodi.title-submissions.index', ['status' => 'TITLE_REVIEW']) }}" class="btn {{ $statusFilter === 'TITLE_REVIEW' ? 'btn-primary' : 'btn-secondary' }}" style="padding:6px 12px;font-size:12px">Menunggu Kaprodi</a>
                <a href="{{ route('prodi.title-submissions.index', ['status' => 'TITLE_APPROVED']) }}" class="btn {{ $statusFilter === 'TITLE_APPROVED' ? 'btn-primary' : 'btn-secondary' }}" style="padding:6px 12px;font-size:12px">Disetujui</a>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th>Judul</th>
                        <th>Diajukan</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($theses as $thesis)
                        <tr>
                            <td class="td-main">{{ $thesis->student->name }} <span class="text-muted" style="font-weight:400">({{ $thesis->student->nim }})</span></td>
                            <td>{{ $thesis->student->studyProgram->name ?? '—' }}</td>
                            <td>{{ $thesis->title->title ?: '—' }}</td>
                            <td style="font-size:12px">{{ $thesis->title->submitted_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $thesis->status->badgeClass() }}">{{ $thesis->status->label() }}</span></td>
                            <td>
                                <div style="display:flex;justify-content:flex-end">
                                    <a href="{{ route('prodi.title-submissions.show', $thesis) }}" class="btn btn-secondary" style="padding:6px 12px">
                                        <i class="fa-solid fa-eye" style="font-size:11px"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada pengajuan judul.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($theses->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $theses->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
