@props(['strength', 'compact' => false])

@php
    $score = $strength['score'];
    $bar = $score >= 80 ? 'bg-green-500' : ($score >= 50 ? 'bg-accent-400' : 'bg-brand-500');
    $label = $score >= 100 ? 'Complete' : ($score >= 80 ? 'Strong' : ($score >= 50 ? 'Getting there' : 'Just started'));
@endphp

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="eyebrow">Profile strength</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $score }}<span class="text-base font-normal text-gray-400">%</span>
                <span class="ml-2 align-middle badge-brand">{{ $label }}</span>
            </p>
        </div>
        @unless ($compact)
            <a href="{{ route('profile.review') }}" class="btn-secondary btn-sm">Improve</a>
        @endunless
    </div>

    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-2 rounded-full {{ $bar }} transition-all" style="width: {{ $score }}%"></div>
    </div>

    @if ($strength['next'] !== [])
        <ul class="mt-3 space-y-1.5 text-sm">
            @foreach ($strength['next'] as $item)
                <li class="flex items-start justify-between gap-3">
                    <a href="{{ route($item['route']) }}" class="link">{{ $item['label'] }}</a>
                    <span class="shrink-0 text-xs text-gray-500">+{{ $item['points'] }}%</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-3 text-sm text-green-700">Every section is filled in. Keep skills current as you learn.</p>
    @endif
</div>
