@php $editing = $academicYear->exists; @endphp

<form method="POST" action="{{ $editing ? route('prodi.academic-years.update', $academicYear) : route('prodi.academic-years.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <x-input-label for="name" value="Tahun Akademik" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $academicYear->name)" required autofocus placeholder="mis. 2025/2026" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="semester" value="Semester" />
                <select id="semester" name="semester" class="f-input" required>
                    <option value="">— Pilih semester —</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester }}" @selected(old('semester', $academicYear->semester) === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('semester')" />
            </div>

            <label style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface2);cursor:pointer">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text)">Jadikan tahun akademik aktif</div>
                    <div class="text-muted" style="font-size:11.5px;margin-top:2px">Mengaktifkan ini otomatis menonaktifkan tahun akademik lain.</div>
                </div>
                <span class="switch">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $academicYear->is_active))>
                    <span class="slider"></span>
                </span>
            </label>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Tahun Akademik' }}
        </button>
        <a href="{{ route('prodi.academic-years.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
