@php $editing = $studyProgram->exists; @endphp

<form method="POST" action="{{ $editing ? route('prodi.study-programs.update', $studyProgram) : route('prodi.study-programs.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <x-input-label for="faculty_id" value="Fakultas" />
                <select id="faculty_id" name="faculty_id" class="f-input" required>
                    <option value="">— Pilih fakultas —</option>
                    @foreach ($faculties as $faculty)
                        <option value="{{ $faculty->id }}" @selected((int) old('faculty_id', $studyProgram->faculty_id) === $faculty->id)>{{ $faculty->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('faculty_id')" />
            </div>

            <div>
                <x-input-label for="name" value="Nama Program Studi" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $studyProgram->name)" required autofocus placeholder="mis. Teknik Informatika" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="code" value="Kode" />
                    <x-text-input id="code" name="code" type="text" :value="old('code', $studyProgram->code)" required placeholder="mis. TI" />
                    <x-input-error :messages="$errors->get('code')" />
                </div>
                <div>
                    <x-input-label for="degree_level" value="Jenjang" />
                    <select id="degree_level" name="degree_level" class="f-input">
                        <option value="">—</option>
                        @foreach ($degreeLevels as $level)
                            <option value="{{ $level }}" @selected(old('degree_level', $studyProgram->degree_level) === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('degree_level')" />
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Program Studi' }}
        </button>
        <a href="{{ route('prodi.study-programs.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
