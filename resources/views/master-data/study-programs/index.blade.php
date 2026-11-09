<x-app-layout>
    <x-slot name="header">Program Studi</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Program Studi</div>
            @if (Auth::user()->hasPermission('tambah.study_program'))
                <a href="{{ route('prodi.study-programs.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Program Studi
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kode</th>
                        <th>Fakultas</th>
                        <th>Jenjang</th>
                        <th>Mahasiswa</th>
                        <th>Dosen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($studyPrograms as $studyProgram)
                        <tr>
                            <td class="td-main">{{ $studyProgram->name }}</td>
                            <td><code style="font-size:12px">{{ $studyProgram->code }}</code></td>
                            <td>{{ $studyProgram->faculty->name }}</td>
                            <td>{{ $studyProgram->degree_level ?? '—' }}</td>
                            <td>{{ $studyProgram->students_count }}</td>
                            <td>{{ $studyProgram->lecturers_count }}</td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.study_program'))
                                        <a href="{{ route('prodi.study-programs.edit', $studyProgram) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.study_program'))
                                        <form method="POST" action="{{ route('prodi.study-programs.destroy', $studyProgram) }}" onsubmit="return confirm('Hapus program studi {{ $studyProgram->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding:6px 12px">
                                                <i class="fa-solid fa-trash" style="font-size:11px"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted" style="text-align:center;padding:24px">Belum ada program studi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
