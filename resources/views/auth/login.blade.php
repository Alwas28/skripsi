<x-guest-layout>
    <div class="auth-subtitle">Masuk untuk mengakses layanan skripsi Anda</div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" style="display:flex;flex-direction:column;gap:16px">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nama@umkendari.ac.id" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Remember Me -->
        <div style="display:flex;align-items:center;justify-content:space-between">
            <label for="remember_me" style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--sub);cursor:pointer">
                <input id="remember_me" type="checkbox" name="remember" style="accent-color:var(--ac)">
                {{ __('Ingat saya') }}
            </label>

            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">
                    {{ __('Lupa password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <i class="fa-solid fa-right-to-bracket"></i>{{ __('Masuk') }}
        </button>
    </form>
</x-guest-layout>
