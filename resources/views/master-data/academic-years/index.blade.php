<x-app-layout>
    <x-slot name="header">Tahun Akademik</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Tahun Akademik</div>
            @if (Auth::user()->hasPermission('tambah.academic_year'))
                <a href="{{ route('prodi.academic-years.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Tahun Akademik
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tahun Akademik</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($academicYears as $academicYear)
                        <tr>
                            <td class="td-main">{{ $academicYear->name }}</td>
                            <td>{{ $academicYear->semester }}</td>
                            <td>
                                @if ($academicYear->is_active)
                                    <span class="badge badge-green"><i class="fa-solid fa-check" style="font-size:9px"></i>Aktif</span>
                                @else
                                    <span class="badge badge-gray">Tidak Aktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.academic_year'))
                                        <a href="{{ route('prodi.academic-years.edit', $academicYear) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.academic_year'))
                                        <form method="POST" action="{{ route('prodi.academic-years.destroy', $academicYear) }}" onsubmit="return confirm('Hapus tahun akademik {{ $academicYear->name }} {{ $academicYear->semester }}?')">
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
                            <td colspan="4" class="text-muted" style="text-align:center;padding:24px">Belum ada tahun akademik.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
