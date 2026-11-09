@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'f-status']) }}>
        {{ $status }}
    </div>
@endif
