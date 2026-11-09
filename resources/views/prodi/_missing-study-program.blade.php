@if (! Auth::user()->isSuperAdmin() && ! Auth::user()->study_program_id)
    <div class="card animate-fadeUp" style="margin-bottom:20px;border-color:rgba(245,158,11,.35)">
        <div class="card-body" style="display:flex;gap:12px">
            <i class="fa-solid fa-triangle-exclamation" style="color:#fbbf24;margin-top:2px"></i>
            <p style="font-size:13px;color:var(--sub)">Akun Anda belum terhubung ke program studi, sehingga data skripsi belum bisa ditampilkan. Hubungi Administrator.</p>
        </div>
    </div>
@endif
