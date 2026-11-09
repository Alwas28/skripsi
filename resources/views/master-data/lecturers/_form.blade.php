@php $editing = $lecturer->exists; $linked = $editing && $lecturer->user_id; @endphp

<form method="POST" action="{{ $editing ? route('prodi.lecturers.update', $lecturer) : route('prodi.lecturers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Data Dosen</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="nidn" value="NIDN" />
                    <x-text-input id="nidn" name="nidn" type="text" :value="old('nidn', $lecturer->nidn)" required autofocus />
                    <x-input-error :messages="$errors->get('nidn')" />
                </div>
                <div>
                    <x-input-label for="no_wa" value="Nomor WA" />
                    <x-text-input id="no_wa" name="no_wa" type="text" :value="old('no_wa', $lecturer->no_wa)" placeholder="mis. 08123456789" />
                    <x-input-error :messages="$errors->get('no_wa')" />
                </div>
            </div>

            <div style="display:grid;grid-template-columns:140px 1fr 140px;gap:16px">
                <div>
                    <x-input-label for="gelar_depan" value="Gelar Depan" />
                    <x-text-input id="gelar_depan" name="gelar_depan" type="text" :value="old('gelar_depan', $lecturer->gelar_depan)" placeholder="mis. Dr." />
                    <x-input-error :messages="$errors->get('gelar_depan')" />
                </div>
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input id="name" name="name" type="text" :value="old('name', $lecturer->name)" required placeholder="Tanpa gelar" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="gelar_belakang" value="Gelar Belakang" />
                    <x-text-input id="gelar_belakang" name="gelar_belakang" type="text" :value="old('gelar_belakang', $lecturer->gelar_belakang)" placeholder="mis. M.Kom" />
                    <x-input-error :messages="$errors->get('gelar_belakang')" />
                </div>
            </div>

            <div>
                <x-input-label for="study_program_id" value="Program Studi" />
                <select id="study_program_id" name="study_program_id" class="f-input">
                    <option value="">— Tidak terikat program studi tertentu —</option>
                    @foreach ($studyPrograms as $studyProgram)
                        <option value="{{ $studyProgram->id }}" @selected((int) old('study_program_id', $lecturer->study_program_id) === $studyProgram->id)>{{ $studyProgram->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('study_program_id')" />
            </div>

            <div>
                <x-input-label for="expertise_field" value="Bidang Keahlian" />
                <x-text-input id="expertise_field" name="expertise_field" type="text" :value="old('expertise_field', $lecturer->expertise_field)" placeholder="mis. Rekayasa Perangkat Lunak" />
                <x-input-error :messages="$errors->get('expertise_field')" />
            </div>

            <label style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface2);cursor:pointer">
                <span style="font-size:13px;font-weight:600;color:var(--text)">Status aktif</span>
                <span class="switch">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $lecturer->exists ? $lecturer->is_active : true))>
                    <span class="slider"></span>
                </span>
            </label>
        </div>
    </div>

    <div class="card animate-fadeUp d1" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-key a-text" style="margin-right:8px"></i>Akun Login</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            @if ($linked)
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface2)">
                    <span class="badge badge-green"><i class="fa-solid fa-link" style="font-size:9px"></i>Terhubung</span>
                    <span style="font-size:13px;color:var(--text)">{{ $lecturer->user->email }}</span>
                </div>
                <p class="text-muted" style="font-size:12px">
                    Dosen ini sudah punya akun login.
                    @if (Auth::user()->hasPermission('edit.user'))
                        Kelola email/password/role akun lewat <a href="{{ route('users.edit', $lecturer->user) }}" class="auth-link">Manajemen User</a>.
                    @endif
                </p>
            @else
                <div>
                    <x-input-label for="user_id" value="Hubungkan ke akun yang sudah ada" />
                    <select id="user_id" name="user_id" class="f-input">
                        <option value="">— Belum terhubung ke akun —</option>
                        @foreach ($availableUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) old('user_id', $lecturer->user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('user_id')" />
                </div>

                <div class="auth-divider" style="margin:2px 0"></div>
                <p class="text-muted" style="font-size:12px;margin-top:-8px">— atau buat akun baru untuk dosen ini —</p>

                <div>
                    <x-input-label for="new_account_email" value="Email" />
                    <x-text-input id="new_account_email" name="new_account_email" type="email" :value="old('new_account_email')" placeholder="dosen@umkendari.ac.id" autocomplete="off" />
                    <x-input-error :messages="$errors->get('new_account_email')" />
                </div>

                <div>
                    <x-input-label value="Role Akun" />
                    @include('users._role-switches', [
                        'name' => 'new_account_roles',
                        'selected' => old('new_account_roles', $roles->whereIn('slug', \App\Models\Role::DOSEN_ROLES)->pluck('id')->all()),
                    ])
                    <x-input-error :messages="$errors->get('new_account_roles')" />
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <x-input-label for="new_account_password" value="Password" />
                        <x-text-input id="new_account_password" name="new_account_password" type="password" autocomplete="new-password" placeholder="Kosongkan jika tidak ingin membuat akun" />
                        <x-input-error :messages="$errors->get('new_account_password')" />
                    </div>
                    <div>
                        <x-input-label for="new_account_password_confirmation" value="Konfirmasi Password" />
                        <x-text-input id="new_account_password_confirmation" name="new_account_password_confirmation" type="password" autocomplete="new-password" />
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px">Isi email &amp; password untuk langsung membuatkan akun login bagi dosen ini — dosen bisa login memakai email tersebut sesuai role yang dipilih.</p>
            @endif
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Dosen' }}
        </button>
        <a href="{{ route('prodi.lecturers.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
