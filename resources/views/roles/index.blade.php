<x-app-layout>
    <x-slot name="header">Role & Akses</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Role</div>
            @if (Auth::user()->hasPermission('tambah.role'))
                <a href="{{ route('roles.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Role
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Slug</th>
                        <th>Grup Menu</th>
                        <th>Jumlah User</th>
                        <th>Jumlah Akses</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="td-main">
                                {{ $role->name }}
                                @if ($role->is_super_admin)
                                    <span class="badge badge-amber" style="margin-left:6px"><i class="fa-solid fa-shield-halved" style="font-size:8px"></i>Super Admin</span>
                                @endif
                            </td>
                            <td><code style="font-size:12px">{{ $role->slug }}</code></td>
                            <td>{{ $role->menu_group ? ucfirst($role->menu_group) : '—' }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.role'))
                                        <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.role') && ! $role->is_super_admin)
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role {{ $role->name }}?')">
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
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada role.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
