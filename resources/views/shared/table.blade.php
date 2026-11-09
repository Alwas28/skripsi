{{--
    Read-only list page. A cell is a string/null, or an array:
    ['badge' => text, 'class' => 'badge-green'] or ['link' => url, 'text' => label].
    $rows is a collection or paginator of row arrays.
--}}
<x-app-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    @include('prodi._missing-study-program-if-needed', ['needsStudyProgram' => $needsStudyProgram ?? false])

    @if (! empty($tabs))
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($tabs as $tab)
                    <a href="{{ $tab['url'] }}" class="btn {{ $tab['active'] ? 'btn-primary' : 'btn-secondary' }}" style="padding:6px 12px;font-size:12px">{{ $tab['label'] }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title">{{ $tableTitle ?? $title }}</div>
        </div>
        <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>
                                    @if (is_array($cell) && isset($cell['badge']))
                                        <span class="badge {{ $cell['class'] ?? 'badge-gray' }}">{{ $cell['badge'] }}</span>
                                    @elseif (is_array($cell) && isset($cell['link']))
                                        <a href="{{ $cell['link'] }}" class="auth-link">{{ $cell['text'] }}</a>
                                    @else
                                        {{ $cell === null || $cell === '' ? '—' : $cell }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-muted" style="text-align:center;padding:24px">{{ $empty ?? 'Belum ada data.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator && $rows->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
