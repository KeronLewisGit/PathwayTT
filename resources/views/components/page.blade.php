{{-- Standard page body: every screen shares the dashboard's width, rhythm and gutters. --}}
<div class="py-6 sm:py-10">
    <div {{ $attributes->merge(['class' => 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6']) }}>
        {{ $slot }}
    </div>
</div>
