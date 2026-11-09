@php $editing = $role->exists; @endphp

<form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Informasi Role</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            @if ($editing && $role->is_super_admin)
                <div class="badge badge-amber" style="align-self:flex-start">
                    <i class="fa-solid fa-shield-halved" style="font-size:9px"></i>Role super admin — selalu punya akses penuh, apa pun switch di bawah
                </div>
            @endif

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="name" value="Nama Role" />
                    <x-text-input id="name" name="name" type="text" :value="old('name', $role->name)" required autofocus placeholder="mis. Admin Prodi" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="slug" value="Slug" />
                    <x-text-input id="slug" name="slug" type="text" :value="old('slug', $role->slug)" required placeholder="mis. admin_prodi" />
                    <x-input-error :messages="$errors->get('slug')" />
                </div>
            </div>

            <div>
                <x-input-label for="description" value="Deskripsi" />
                <x-text-input id="description" name="description" type="text" :value="old('description', $role->description)" placeholder="Opsional" />
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div>
                <x-input-label for="menu_group" value="Grup Menu Sidebar" />
                <select id="menu_group" name="menu_group" class="f-input">
                    <option value="" @selected(old('menu_group', $role->menu_group) === null)>— Tidak ada (hanya menu izin) —</option>
                    <option value="mahasiswa" @selected(old('menu_group', $role->menu_group) === 'mahasiswa')>Mahasiswa</option>
                    <option value="dosen" @selected(old('menu_group', $role->menu_group) === 'dosen')>Dosen</option>
                    <option value="prodi" @selected(old('menu_group', $role->menu_group) === 'prodi')>Prodi</option>
                </select>
                <p class="text-muted" style="font-size:12px;margin-top:6px">Menentukan menu sidebar mana yang dilihat role ini (lihat config/menu.php).</p>
                <x-input-error :messages="$errors->get('menu_group')" />
            </div>
        </div>
    </div>

    <div class="card animate-fadeUp d1" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-toggle-on a-text" style="margin-right:8px"></i>Hak Akses</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:20px">
            @foreach ($permissionGroups as $feature => $group)
                <div>
                    <p style="font-size:12px;font-weight:600;letter-spacing:.3px;color:var(--sub);margin-bottom:10px">{{ $group['label'] }}</p>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
                        @foreach ($group['permissions'] as $permission)
                            <label style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface2);cursor:pointer">
                                <span style="font-size:13px;color:var(--text)">{{ $permission->action_label }}</span>
                                <span class="switch">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                        @checked(in_array($permission->id, old('permissions', $rolePermissionIds)))>
                                    <span class="slider"></span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @if ($editing && $role->is_super_admin)
                <p class="text-muted" style="font-size:12px">Role super admin tetap punya akses penuh terlepas dari switch di atas — daftar ini cuma dipakai kalau nanti bypass super admin dicabut.</p>
            @endif
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Role' }}
        </button>
        <a href="{{ route('roles.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
