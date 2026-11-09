<x-guest-layout>
    <div style="margin-bottom:16px">
        <h2 class="font-display" style="font-size:18px;font-weight:700;color:var(--text)">Ubah Password</h2>
        <p class="text-muted" style="font-size:13px;margin-top:6px">Anda masih memakai password awal. Buat password baru untuk melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.force.update') }}" style="display:flex;flex-direction:column;gap:16px">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="password" value="Password Baru" />
            <x-text-input id="password" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Konfirmasi Password Baru" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <button type="submit" class="btn btn-primary btn-block">Simpan Password</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:12px">
        @csrf
        <button type="submit" class="btn btn-secondary btn-block">Keluar</button>
    </form>
</x-guest-layout>
