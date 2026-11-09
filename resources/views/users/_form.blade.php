@php $editing = $user->exists; @endphp

<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <x-input-label for="name" value="Nama" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-input-label value="Role" />
                <p class="text-muted" style="font-size:12px;margin:2px 0 10px">Satu user bisa memiliki lebih dari satu role.</p>
                @include('users._role-switches', [
                    'name' => 'roles',
                    'selected' => old('roles', $editing ? $user->roles->pluck('id')->all() : []),
                ])
                <x-input-error :messages="$errors->get('roles')" />
            </div>

            <div>
                <x-input-label for="study_program_id" value="Program Studi yang Dikelola" />
                <select id="study_program_id" name="study_program_id" class="f-input">
                    <option value="">— Tidak ada —</option>
                    @foreach ($studyPrograms as $studyProgram)
                        <option value="{{ $studyProgram->id }}" @selected((int) old('study_program_id', $user->study_program_id) === $studyProgram->id)>{{ $studyProgram->name }}</option>
                    @endforeach
                </select>
                <p class="text-muted" style="font-size:12px;margin-top:6px">Wajib diisi untuk Admin Prodi dan Kaprodi, agar mereka bisa melihat data skripsi program studinya.</p>
                <x-input-error :messages="$errors->get('study_program_id')" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="password" :value="$editing ? 'Password Baru' : 'Password'" />
                    <x-text-input id="password" name="password" type="password" autocomplete="new-password" :required="! $editing" placeholder="{{ $editing ? 'Kosongkan jika tidak diubah' : '' }}" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Konfirmasi Password" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" :required="! $editing" />
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah User' }}
        </button>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
