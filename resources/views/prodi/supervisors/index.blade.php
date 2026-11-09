<x-app-layout>
    <x-slot name="header">Penetapan Pembimbing</x-slot>

    @include('prodi._missing-study-program')

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,260px));gap:16px">
        <div class="stat-card animate-fadeUp">
            <div class="stat-icon a-bg-lt a-text"><i class="fa-solid fa-user-plus"></i></div>
            <div>
                <div class="stat-num">{{ $awaitingProposalCount }}</div>
                <div class="stat-label">Perlu Diusulkan</div>
            </div>
        </div>
        <div class="stat-card animate-fadeUp">
            <div class="stat-icon a-bg-lt a-text"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <div class="stat-num">{{ $awaitingApprovalCount }}</div>
                <div class="stat-label">Menunggu Kaprodi</div>
            </div>
        </div>
    </div>

    <div class="card animate-fadeUp d1" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title">Daftar Skripsi dengan Judul Disetujui</div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Judul</th>
                        <th>Pembimbing 1</th>
                        <th>Pembimbing 2</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($theses as $thesis)
                        @php
                            $supervisor1 = $thesis->supervisors->firstWhere('position', 1);
                            $supervisor2 = $thesis->supervisors->firstWhere('position', 2);
                        @endphp
                        <tr>
                            <td class="td-main">{{ $thesis->student->name }} <span class="text-muted" style="font-weight:400">({{ $thesis->student->nim }})</span></td>
                            <td>{{ $thesis->title->title ?: '—' }}</td>
                            <td>{{ $supervisor1?->lecturer->full_name ?? '—' }}</td>
                            <td>{{ $supervisor2?->lecturer->full_name ?? '—' }}</td>
                            <td>
                                @if ($supervisor1)
                                    <span class="badge {{ $supervisor1->status->badgeClass() }}">{{ $supervisor1->status->label() }}</span>
                                @else
                                    <span class="badge badge-gray">Belum diusulkan</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end">
                                    <a href="{{ route('prodi.supervisors.show', $thesis) }}" class="btn btn-secondary" style="padding:6px 12px">
                                        <i class="fa-solid fa-eye" style="font-size:11px"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada judul yang disetujui.</td>
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
