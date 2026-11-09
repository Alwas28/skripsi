<x-app-layout>
    <x-slot name="header">Pengajuan Judul</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px;padding:56px 24px">
            <div class="badge-red" style="width:56px;height:56px;border-radius:16px;display:grid;place-items:center;font-size:22px;background:rgba(239,68,68,.15);color:#f87171">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <div>
                <h2 class="font-display" style="font-size:17px;font-weight:600;color:var(--text)">Akun Anda Belum Terhubung</h2>
                <p class="text-muted" style="font-size:13px;margin-top:6px;max-width:420px">
                    Akun login Anda belum dihubungkan ke data mahasiswa. Hubungi Admin Prodi untuk menghubungkan akun Anda ke NIM yang sesuai sebelum bisa mengajukan judul skripsi.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
