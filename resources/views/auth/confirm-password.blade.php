<x-guest-layout>
    <div class="text-muted" style="font-size:13px;margin-bottom:18px">
        {{ __('Ini adalah area aman aplikasi. Mohon konfirmasi password Anda sebelum melanjutkan.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" style="display:flex;flex-direction:column;gap:16px">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            {{ __('Konfirmasi') }}
        </button>
    </form>
</x-guest-layout>
