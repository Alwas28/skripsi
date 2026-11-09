@php $editing = $room->exists; @endphp

<form method="POST" action="{{ $editing ? route('prodi.rooms.update', $room) : route('prodi.rooms.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <x-input-label for="name" value="Nama Ruang" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $room->name)" required autofocus placeholder="mis. Ruang Sidang 1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <x-input-label for="code" value="Kode" />
                    <x-text-input id="code" name="code" type="text" :value="old('code', $room->code)" required placeholder="mis. RS-1" />
                    <x-input-error :messages="$errors->get('code')" />
                </div>
                <div>
                    <x-input-label for="capacity" value="Kapasitas" />
                    <x-text-input id="capacity" name="capacity" type="number" min="1" :value="old('capacity', $room->capacity)" />
                    <x-input-error :messages="$errors->get('capacity')" />
                </div>
            </div>
            <div>
                <x-input-label for="building" value="Gedung" />
                <x-text-input id="building" name="building" type="text" :value="old('building', $room->building)" />
                <x-input-error :messages="$errors->get('building')" />
            </div>
            <label style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text)">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $room->is_active : true))>
                Ruang aktif dan bisa dijadwalkan
            </label>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:20px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check"></i>{{ $editing ? 'Simpan Perubahan' : 'Tambah Ruang' }}
        </button>
        <a href="{{ route('prodi.rooms.index') }}" class="btn btn-secondary">Batal</a>
    </div>
</form>
