<x-app-layout>
    <x-slot name="header">Fakultas</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Fakultas</div>
            @if (Auth::user()->hasPermission('tambah.faculty'))
                <a href="{{ route('prodi.faculties.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Fakultas
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kode</th>
                        <th>Jumlah Prodi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($faculties as $faculty)
                        <tr>
                            <td class="td-main">{{ $faculty->name }}</td>
                            <td><code style="font-size:12px">{{ $faculty->code }}</code></td>
                            <td>{{ $faculty->study_programs_count }}</td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.faculty'))
                                        <a href="{{ route('prodi.faculties.edit', $faculty) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.faculty'))
                                        <form method="POST" action="{{ route('prodi.faculties.destroy', $faculty) }}" onsubmit="return confirm('Hapus fakultas {{ $faculty->name }}?')">
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
                            <td colspan="4" class="text-muted" style="text-align:center;padding:24px">Belum ada fakultas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
