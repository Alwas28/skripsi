@props(['value'])

<label {{ $attributes->merge(['class' => 'f-label']) }}>
    {{ $value ?? $slot }}
</label>
