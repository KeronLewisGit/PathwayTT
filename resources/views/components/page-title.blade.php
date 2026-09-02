@props(['title', 'description' => null])

{{-- Header-slot content: title, optional one-line description, optional actions on the right. --}}
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold leading-tight text-gray-900 truncate">{{ $title }}</h1>
        @if ($description)
            <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
