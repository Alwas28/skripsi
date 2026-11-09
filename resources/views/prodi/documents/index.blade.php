<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    @include('prodi._missing-study-program')

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Dokumen Skripsi Mahasiswa</div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th>Judul</th>
                        <th>Jumlah Dokumen</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($theses as $thesis)
                        <tr>
                            <td class="td-main">{{ $thesis->student->name }} <span class="text-muted" style="font-weight:400">({{ $thesis->student->nim }})</span></td>
                            <td>{{ $thesis->student->studyProgram->name ?? '—' }}</td>
                            <td>{{ $thesis->title?->title ?: '—' }}</td>
                            <td>{{ $thesis->documents_count }}</td>
                            <td><span class="badge {{ $thesis->status->badgeClass() }}">{{ $thesis->status->label() }}</span></td>
                            <td>
                                <div style="display:flex;justify-content:flex-end">
                                    <a href="{{ route('prodi.documents.show', $thesis) }}" class="btn btn-secondary" style="padding:6px 12px" aria-label="Lihat dokumen {{ $thesis->student->name }}">
                                        <i class="fa-solid fa-eye" style="font-size:11px"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada dokumen mahasiswa.</td>
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
