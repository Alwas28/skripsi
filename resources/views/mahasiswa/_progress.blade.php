<div>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--sub);margin-bottom:6px">
        <span>Progres skripsi</span><span>{{ $percent }}%</span>
    </div>
    <div style="height:8px;border-radius:4px;background:var(--surface2);overflow:hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="a-grad" style="height:100%;width:{{ $percent }}%"></div>
    </div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;font-size:13px;color:var(--text)">
    @foreach ($milestones as $milestone)
        <div>
            <i class="fa-solid {{ $milestone['done'] ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $milestone['done'] ? '#34d399' : 'var(--muted)' }};margin-right:6px"></i>{{ $milestone['label'] }}
        </div>
    @endforeach
</div>
