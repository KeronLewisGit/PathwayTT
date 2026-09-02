@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-accent-400 text-start text-base font-medium text-white bg-brand-800 focus:outline-none focus:bg-brand-900 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-brand-100 hover:text-white hover:bg-brand-800 hover:border-brand-500 focus:outline-none focus:text-white focus:bg-brand-800 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
