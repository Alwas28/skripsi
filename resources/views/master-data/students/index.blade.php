<x-app-layout>
    <x-slot name="header">Mahasiswa</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Mahasiswa</div>
            @if (Auth::user()->hasPermission('tambah.student'))
                <a href="{{ route('prodi.students.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Mahasiswa
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <th>Dosen PA</th>
                        <th>Angkatan</th>
                        <th>Status</th>
                        <th>Akun</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td><code style="font-size:12px">{{ $student->nim }}</code></td>
                            <td class="td-main">{{ $student->name }}</td>
                            <td>{{ $student->studyProgram->name }}</td>
                            <td>
                                @if ($student->academicAdvisor)
                                    {{ $student->academicAdvisor->full_name }}
                                @else
                                    <span class="badge badge-gray">Belum ditentukan</span>
                                @endif
                            </td>
                            <td>{{ $student->entry_year }}</td>
                            <td>
                                <span class="badge {{ match($student->status) {
                                    'aktif' => 'badge-green',
                                    'lulus' => 'badge-blue',
                                    'cuti' => 'badge-amber',
                                    default => 'badge-gray',
                                } }}">{{ ucfirst($student->status) }}</span>
                            </td>
                            <td>
                                @if ($student->user)
                                    <span class="text-muted" style="font-size:12px">{{ $student->user->email }}</span>
                                @else
                                    <span class="badge badge-gray">Belum terhubung</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.student'))
                                        <a href="{{ route('prodi.students.edit', $student) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.student'))
                                        <form method="POST" action="{{ route('prodi.students.destroy', $student) }}" onsubmit="return confirm('Hapus mahasiswa {{ $student->name }}?')">
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
                            <td colspan="8" class="text-muted" style="text-align:center;padding:24px">Belum ada mahasiswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($students->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
