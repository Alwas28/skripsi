@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'f-input']) }}>
