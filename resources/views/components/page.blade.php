@props(['width' => 'default'])

@php
    $max = match ($width) {
        'narrow' => 'max-w-4xl',
        'wide' => 'max-w-7xl',
        default => 'max-w-6xl',
    };
@endphp

{{-- Standard page body: consistent vertical rhythm and gutters on every screen size. --}}
<div class="py-6 sm:py-10">
    <div {{ $attributes->merge(['class' => "{$max} mx-auto px-4 sm:px-6 lg:px-8 space-y-6"]) }}>
        {{ $slot }}
    </div>
</div>
