{{-- PathwayTT wordmark: a compass-like mark + name. Inherits text colour. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold tracking-tight']) }}>
    <svg class="h-7 w-7 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="16" cy="16" r="14" class="fill-current opacity-15" />
        <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2" />
        <path d="M16 6 L19.5 14.5 L28 16 L19.5 17.5 L16 26 L12.5 17.5 L4 16 L12.5 14.5 Z" class="fill-current" />
        <circle cx="16" cy="16" r="2.4" fill="white" />
    </svg>
    <span class="text-lg leading-none">Pathway<span class="font-normal opacity-80">TT</span></span>
</span>
