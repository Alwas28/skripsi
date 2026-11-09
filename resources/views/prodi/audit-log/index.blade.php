<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    @php
        $formatValue = fn ($value) => match (true) {
            is_array($value) => implode(', ', $value) ?: '—',
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            $value === null || $value === '' => '—',
            default => \Illuminate\Support\Str::limit((string) $value, 120),
        };
    @endphp

    <div class="card animate-fadeUp">
        <form method="GET" action="{{ route('prodi.audit-log') }}">
            <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
                <div>
                    <x-input-label for="module" value="Modul" />
                    <select id="module" name="module" class="f-input">
                        <option value="">Semua modul</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? null) === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="user" value="Pengguna" />
                    <x-text-input id="user" name="user" type="text" :value="$filters['user'] ?? ''" placeholder="Nama pengguna" />
                </div>
                <div>
                    <x-input-label for="from" value="Dari Tanggal" />
                    <x-text-input id="from" name="from" type="date" :value="$filters['from'] ?? ''" />
                    <x-input-error :messages="$errors->get('from')" />
                </div>
                <div>
                    <x-input-label for="to" value="Sampai Tanggal" />
                    <x-text-input id="to" name="to" type="date" :value="$filters['to'] ?? ''" />
                    <x-input-error :messages="$errors->get('to')" />
                </div>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i>Terapkan</button>
                    <a href="{{ route('prodi.audit-log') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card animate-fadeUp d1" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-shield-halved a-text" style="margin-right:8px"></i>Jejak Aktivitas</div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Data</th>
                        <th>Perubahan</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td style="font-size:12px;white-space:nowrap">{{ $log->created_at->format('d-m-Y H:i') }}</td>
                            <td class="td-main">{{ $log->user_name }}</td>
                            <td>{{ $log->action }}</td>
                            <td style="white-space:nowrap">{{ $log->module }} #{{ $log->record_id }}</td>
                            <td style="font-size:12px">
                                @foreach (array_keys(($log->new_values ?? []) + ($log->old_values ?? [])) as $field)
                                    <div>
                                        <code style="font-size:11px">{{ $field }}</code>:
                                        {{ $formatValue($log->old_values[$field] ?? null) }}
                                        <i class="fa-solid fa-arrow-right" style="font-size:9px;color:var(--muted);margin:0 4px"></i>
                                        {{ $formatValue($log->new_values[$field] ?? null) }}
                                    </div>
                                @endforeach
                            </td>
                            <td style="font-size:12px">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted" style="text-align:center;padding:24px">Belum ada aktivitas yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
