<x-guest-layout>
    <div class="text-muted" style="font-size:13px;margin-bottom:18px">
        {{ __('Lupa password Anda? Tidak masalah. Masukkan alamat email Anda dan kami akan mengirimkan tautan untuk membuat password baru.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" style="display:flex;flex-direction:column;gap:16px">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus placeholder="nama@umkendari.ac.id" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            {{ __('Kirim Tautan Reset Password') }}
        </button>
    </form>
</x-guest-layout>
