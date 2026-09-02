@props(['message' => null, 'tone' => 'success'])

@if ($message)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium '.($tone === 'success' ? 'text-green-700' : 'text-gray-700')]) }} role="status">
        @if ($tone === 'success')
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42L8.5 12.09l6.79-6.8a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        @endif
        {{ $message }}
    </span>
@endif
