<x-guest-layout>
    <div class="text-muted" style="font-size:13px;margin-bottom:18px">
        {{ __('Terima kasih telah mendaftar! Sebelum memulai, mohon verifikasi alamat email Anda dengan mengklik tautan yang baru saja kami kirimkan. Jika Anda tidak menerima email, kami akan dengan senang hati mengirimkan yang baru.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="f-status" style="margin-bottom:18px">
            {{ __('Tautan verifikasi baru telah dikirim ke alamat email Anda.') }}
        </div>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                {{ __('Kirim Ulang Email Verifikasi') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="auth-link" style="background:none;border:none;cursor:pointer;font-family:inherit">
                {{ __('Keluar') }}
            </button>
        </form>
    </div>
</x-guest-layout>
