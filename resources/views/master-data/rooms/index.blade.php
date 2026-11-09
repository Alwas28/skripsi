<x-app-layout>
    <x-slot name="header">Ruang</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Ruang</div>
            @if (Auth::user()->hasPermission('tambah.room'))
                <a href="{{ route('prodi.rooms.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Ruang
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Kode</th>
                        <th>Gedung</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $room)
                        <tr>
                            <td class="td-main">{{ $room->name }}</td>
                            <td><code style="font-size:12px">{{ $room->code }}</code></td>
                            <td>{{ $room->building ?: '—' }}</td>
                            <td>{{ $room->capacity ?: '—' }}</td>
                            <td><span class="badge {{ $room->is_active ? 'badge-green' : 'badge-gray' }}">{{ $room->is_active ? 'Aktif' : 'Tidak aktif' }}</span></td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.room'))
                                        <a href="{{ route('prodi.rooms.edit', $room) }}" class="btn btn-secondary" style="padding:6px 12px" aria-label="Edit {{ $room->name }}">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.room'))
                                        <form method="POST" action="{{ route('prodi.rooms.destroy', $room) }}" onsubmit="return confirm('Hapus ruang {{ $room->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding:6px 12px" aria-label="Hapus {{ $room->name }}">
                                                <i class="fa-solid fa-trash" style="font-size:11px"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada ruang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
