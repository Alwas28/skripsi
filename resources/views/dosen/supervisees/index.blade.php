<x-app-layout>
    <x-slot name="header">Mahasiswa Bimbingan</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Mahasiswa Bimbingan</div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th>Judul</th>
                        <th>Peran</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="td-main">{{ $assignment->thesis->student->name }} <span class="text-muted" style="font-weight:400">({{ $assignment->thesis->student->nim }})</span></td>
                            <td>{{ $assignment->thesis->student->studyProgram->name ?? '—' }}</td>
                            <td>{{ $assignment->thesis->title->title ?: '—' }}</td>
                            <td>Pembimbing {{ $assignment->position }}</td>
                            <td><span class="badge {{ $assignment->thesis->status->badgeClass() }}">{{ $assignment->thesis->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted" style="text-align:center;padding:24px">Belum ada mahasiswa bimbingan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assignments->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
