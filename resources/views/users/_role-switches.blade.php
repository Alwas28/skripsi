<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
    @foreach ($roles as $role)
        <label style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface2);cursor:pointer">
            <span style="font-size:13px;color:var(--text)">{{ $role->name }}</span>
            <span class="switch">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $role->id }}" @checked(in_array($role->id, $selected))>
                <span class="slider"></span>
            </span>
        </label>
    @endforeach
</div>
