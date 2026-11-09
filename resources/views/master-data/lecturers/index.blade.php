<x-app-layout>
    <x-slot name="header">Dosen</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">Daftar Dosen</div>
            @if (Auth::user()->hasPermission('tambah.lecturer'))
                <a href="{{ route('prodi.lecturers.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>Tambah Dosen
                </a>
            @endif
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>NIDN</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <th>Bidang Keahlian</th>
                        <th>Status</th>
                        <th>Akun</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lecturers as $lecturer)
                        <tr>
                            <td><code style="font-size:12px">{{ $lecturer->nidn }}</code></td>
                            <td class="td-main">{{ $lecturer->full_name }}</td>
                            <td>{{ $lecturer->studyProgram->name ?? '—' }}</td>
                            <td>{{ $lecturer->expertise_field ?? '—' }}</td>
                            <td>
                                @if ($lecturer->is_active)
                                    <span class="badge badge-green"><i class="fa-solid fa-check" style="font-size:9px"></i>Aktif</span>
                                @else
                                    <span class="badge badge-gray">Tidak Aktif</span>
                                @endif
                            </td>
                            <td>
                                @if ($lecturer->user)
                                    <span class="text-muted" style="font-size:12px">{{ $lecturer->user->email }}</span>
                                @else
                                    <span class="badge badge-gray">Belum terhubung</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;justify-content:flex-end;gap:8px">
                                    @if (! $lecturer->user_id && Auth::user()->hasPermission('edit.lecturer'))
                                        <button type="button" class="btn btn-secondary" style="padding:6px 12px" title="Hubungkan Akun"
                                            onclick="openConnectAccountModal('{{ route('prodi.lecturers.connect-account', $lecturer) }}', {{ $lecturer->id }}, @js($lecturer->full_name))">
                                            <i class="fa-solid fa-link" style="font-size:11px"></i>
                                        </button>
                                    @endif
                                    @if (Auth::user()->hasPermission('edit.lecturer'))
                                        <a href="{{ route('prodi.lecturers.edit', $lecturer) }}" class="btn btn-secondary" style="padding:6px 12px">
                                            <i class="fa-solid fa-pen" style="font-size:11px"></i>
                                        </a>
                                    @endif
                                    @if (Auth::user()->hasPermission('hapus.lecturer'))
                                        <form method="POST" action="{{ route('prodi.lecturers.destroy', $lecturer) }}" onsubmit="return confirm('Hapus dosen {{ $lecturer->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding:6px 12px">
                                                <i class="fa-solid fa-trash" style="font-size:11px"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted" style="text-align:center;padding:24px">Belum ada dosen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($lecturers->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $lecturers->links() }}
            </div>
        @endif
    </div>

    <!-- ══════ Quick-connect modal ══════ -->
    <div class="modal-backdrop" id="connect-modal" onclick="if(event.target===this)closeModal('connect-modal')">
        <div class="modal-box">
            <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="font-display" style="font-size:16px;font-weight:700;color:var(--text)">Hubungkan Akun</div>
                    <div class="text-muted" id="connect-modal-name" style="font-size:12px;margin-top:1px"></div>
                </div>
                <button type="button" onclick="closeModal('connect-modal')"
                    style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;color:var(--muted);font-size:13px">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" id="connect-account-form">
                @csrf
                <input type="hidden" name="_lecturer_id" id="connect-modal-lecturer-id">
                <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px">
                    <div>
                        <x-input-label for="connect-email" value="Email" />
                        <x-text-input id="connect-email" name="email" type="email" :value="old('email')" required placeholder="dosen@umkendari.ac.id" autocomplete="off" />
                        <x-input-error :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label value="Role Akun" />
                        @include('users._role-switches', [
                            'name' => 'roles',
                            'selected' => old('roles', $roles->whereIn('slug', \App\Models\Role::DOSEN_ROLES)->pluck('id')->all()),
                        ])
                        <x-input-error :messages="$errors->get('roles')" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div>
                            <x-input-label for="connect-password" value="Password" />
                            <x-text-input id="connect-password" name="password" type="password" required autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>
                        <div>
                            <x-input-label for="connect-password-confirmation" value="Konfirmasi Password" />
                            <x-text-input id="connect-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                        </div>
                    </div>
                </div>
                <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" onclick="closeModal('connect-modal')" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i>Hubungkan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openConnectAccountModal(url, lecturerId, name) {
            document.getElementById('connect-account-form').action = url;
            document.getElementById('connect-modal-lecturer-id').value = lecturerId;
            document.getElementById('connect-modal-name').textContent = name;
            openModal('connect-modal');
        }

        @if ($reopenConnectFor)
            document.addEventListener('DOMContentLoaded', () => {
                openConnectAccountModal(
                    @js(route('prodi.lecturers.connect-account', $reopenConnectFor)),
                    {{ $reopenConnectFor->id }},
                    @js($reopenConnectFor->full_name)
                );
            });
        @endif
    </script>
</x-app-layout>
