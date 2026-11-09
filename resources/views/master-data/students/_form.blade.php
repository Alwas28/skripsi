@php $editing = $student->exists; @endphp

<form method="POST" action="{{ $editing ? route('prodi.students.update', $student) : route('prodi.students.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="nim" value="NIM" />
                    <x-text-input id="nim" name="nim" type="text" :value="old('nim', $student->nim)" required autofocus />
                    <x-input-error :messages="$errors->get('nim')" />
                </div>
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input id="name" name="name" type="text" :value="old('name', $student->name)" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
            </div>

            <div>
                <x-input-label for="study_program_id" value="Program Studi" />
                <select id="study_program_id" name="study_program_id" class="f-input" required>
                    <option value="">— Pilih program studi —</option>
                    @foreach ($studyPrograms as $studyProgram)
                        <option value="{{ $studyProgram->id }}" @selected((int) old('study_program_id', $student->study_program_id) === $studyProgram->id)>{{ $studyProgram->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('study_program_id')" />
            </div>

            <div>
                <x-input-label for="academic_advisor_id" value="Dosen Penasehat Akademik (PA)" />
                <select id="academic_advisor_id" name="academic_advisor_id" class="f-input">
                    <option value="">— Belum ditentukan —</option>
                    @foreach ($advisors as $advisor)
                        <option value="{{ $advisor->id }}" @selected((int) old('academic_advisor_id', $student->academic_advisor_id) === $advisor->id)>{{ $advisor->full_name }} ({{ $advisor->nidn }})</option>
                    @endforeach
                </select>
                <p class="text-muted" style="font-size:12px;margin-top:6px">Pilihan berisi dosen aktif yang akunnya memiliki role Dosen PA. Mahasiswa baru bisa mengajukan judul setelah Dosen PA ditentukan.</p>
                <x-input-error :messages="$errors->get('academic_advisor_id')" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="entry_year" value="Tahun Masuk" />
                    <x-text-input id="entry_year" name="entry_year" type="text" :value="old('entry_year', $student->entry_year)" required placeholder="mis. 2022" maxlength="4" />
                    <x-input-error :messages="$errors->get('entry_year')" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="f-input" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status', $student->status ?: 'aktif') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" />
                </div>
            </div>

            <div>
                <x-input-label for="email" value="Email Login" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $student->user?->email)" required autocomplete="off" placeholder="mahasiswa@umkendari.ac.id" />
                <p class="text-muted" style="font-size:12px;margin-top:6px">{{ $student->user ? 'Email yang dipakai mahasiswa untuk login.' : 'Akun login dibuat otomatis dengan password awal NIM. Mahasiswa wajib menggantinya saat pertama login.' }}</p>
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-input-label for="no_wa" value="Nomor WhatsApp" />
                <x-text-input id="no_wa" name="no_wa" type="text" :value="old('no_wa', $student->no_wa)" placeholder="mis. 081234567890" />
                <x-input-error :messages="$errors->get('no_wa')" />
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Mahasiswa' }}
        </button>
        <a href="{{ route('prodi.students.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
