<x-app-layout>
    <x-slot name="header">Manajemen User</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar User</div>
            @if (Auth::user()->hasPermission('tambah.user'))
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah User
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="td-main">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-blue">{{ $role->name }}</span>
                                @empty
                                    <span class="badge badge-gray">Belum diatur</span>
                                @endforelse
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (Auth::user()->hasPermission('edit.user'))
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.user') && ! $user->is(Auth::user()))
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus user {{ $user->name }}?')">
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
                            <td colspan="4" class="text-muted" style="text-align:center;padding:24px">Belum ada user.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
