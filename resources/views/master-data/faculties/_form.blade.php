@php $editing = $faculty->exists; @endphp

<form method="POST" action="{{ $editing ? route('prodi.faculties.update', $faculty) : route('prodi.faculties.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <x-input-label for="name" value="Nama Fakultas" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $faculty->name)" required autofocus placeholder="mis. Fakultas Teknik" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <x-input-label for="code" value="Kode" />
                <x-text-input id="code" name="code" type="text" :value="old('code', $faculty->code)" required placeholder="mis. FT" />
                <x-input-error :messages="$errors->get('code')" />
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Fakultas' }}
        </button>
        <a href="{{ route('prodi.faculties.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
