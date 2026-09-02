<x-app-layout title="Dashboard">
    <x-slot name="header">
        <x-page-title title="Dashboard" description="Welcome back, {{ Auth::user()->name }}. Here's where your search stands.">
            <span class="badge-brand">{{ $board['level']['name'] }} · {{ $board['points'] }} pts</span>
        </x-page-title>
    </x-slot>

    <x-page>
        {{-- Next step --}}
        <div class="card p-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-l-4 border-l-accent-400">
            <div>
                <p class="eyebrow">Next step</p>
                <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $nextStep['title'] }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ $nextStep['body'] }}</p>
            </div>
            <a href="{{ route($nextStep['route']) }}" class="btn-primary shrink-0">{{ $nextStep['cta'] }} →</a>
        </div>

        {{-- Strength + weekly goal + momentum --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-profile-strength :strength="$strength" />

            <div class="card p-5">
                <p class="eyebrow">This week's goal</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $weekly['count'] }}<span class="text-base font-normal text-gray-400"> / {{ $weekly['goal'] }} applied</span></p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-2 rounded-full {{ $weekly['met'] ? 'bg-green-500' : 'bg-brand-500' }}" style="width: {{ $weekly['progress'] }}%"></div>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    @if ($weekly['met'])
                        Goal met this week. Steady effort like this is what turns matches into interviews.
                    @elseif ($weekly['count'] > 0)
                        {{ $weekly['goal'] - $weekly['count'] }} more to go. <a href="{{ route('matches.index') }}" class="link">Pick from your matches</a>.
                    @else
                        Aim for {{ $weekly['goal'] }} applications a week. <a href="{{ route('matches.index') }}" class="link">Start with your best match</a>.
                    @endif
                </p>
            </div>

            <div class="card p-5">
                <p class="eyebrow">Momentum</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $board['level']['name'] }}</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-accent-400" style="width: {{ $board['level']['progress'] }}%"></div>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    {{ $board['points'] }} points from {{ $board['earned_count'] }} of {{ $board['total_count'] }} milestones.
                    @if ($board['level']['next'])
                        {{ $board['level']['next_at'] - $board['points'] }} more to reach <strong>{{ $board['level']['next'] }}</strong>.
                    @else
                        Top level reached.
                    @endif
                </p>
            </div>
        </div>

        {{-- Live status --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('matches.index') }}" class="card p-5 hover:ring-brand-300">
                <p class="eyebrow">Best match</p>
                <p class="mt-2 text-3xl font-semibold {{ $best >= $threshold ? 'text-green-700' : 'text-gray-900' }}">{{ $best }}<span class="text-base font-normal text-gray-400"> / 100</span></p>
                <p class="mt-1 text-xs text-gray-500">
                    @if ($journey && $journey['now'] > $journey['start'])
                        Up {{ $journey['now'] - $journey['start'] }} since your first plan ({{ $journey['since']->diffForHumans() }})
                    @elseif ($lastComputed)
                        {{ $aboveThreshold }} of {{ $eligibleCount }} eligible listings at or above {{ $threshold }}
                    @else
                        Not computed yet
                    @endif
                </p>
            </a>

            <a href="{{ route('matches.index') }}" class="card p-5 hover:ring-brand-300">
                <p class="eyebrow">Eligible listings</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $eligibleCount }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    @if ($lastComputed)
                        {{ $aboveThreshold }} score {{ $threshold }} or more
                    @else
                        Open jobs you can apply to from T&amp;T
                    @endif
                </p>
            </a>

            <a href="{{ route('applications.index') }}" class="card p-5 hover:ring-brand-300">
                <p class="eyebrow">In progress</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $activeApplications }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $applicationTotal }} tracked ·
                    {{ $applications[App\Enums\ApplicationStatus::Offer->value] ?? 0 }} {{ Str::plural('offer', $applications[App\Enums\ApplicationStatus::Offer->value] ?? 0) }}
                </p>
            </a>

            <a href="{{ route('plan.index') }}" class="card p-5 hover:ring-brand-300">
                <p class="eyebrow">Skills plan</p>
                @if ($plan)
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $planClosed }}<span class="text-base font-normal text-gray-400"> / {{ $planGapCount }}</span></p>
                    <p class="mt-1 text-xs text-gray-500">gaps closed · plan from {{ $plan->generated_at->diffForHumans() }}</p>
                @else
                    <p class="mt-2 text-3xl font-semibold text-gray-400">—</p>
                    <p class="mt-1 text-xs text-gray-500">Generated on your first visit</p>
                @endif
            </a>
        </div>

        {{-- Milestones --}}
        <section class="card">
            <div class="card-header flex items-center justify-between">
                <div>
                    <h3 class="card-title">Milestones</h3>
                    <p class="text-xs text-gray-500">Earned by real progress in your search, not time in the app.</p>
                </div>
                <span class="text-sm text-gray-500">{{ $board['earned_count'] }} / {{ $board['total_count'] }}</span>
            </div>
            <ul class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-3 lg:grid-cols-5 rounded-b-lg overflow-hidden">
                @foreach ($board['items'] as $item)
                    <li class="bg-white p-4 {{ $item['earned'] ? '' : 'opacity-60' }}" title="{{ $item['hint'] }}">
                        <div class="flex items-center gap-2">
                            <span class="text-xl {{ $item['earned'] ? '' : 'grayscale' }}" aria-hidden="true">{{ $item['icon'] }}</span>
                            @if ($item['new'])
                                <span class="badge-warning">New</span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ $item['title'] }}</p>
                        <p class="text-xs text-gray-500">
                            @if ($item['earned'])
                                {{ $item['earned_at']->timezone(config('app.display_timezone'))->format('d M') }} · +{{ $item['points'] }} pts
                            @else
                                {{ $item['hint'] }} · +{{ $item['points'] }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="text-xs text-gray-500">
            Match scores recompute automatically when you edit your profile or preferences, and nightly as new listings arrive.
        </p>
    </x-page>
</x-app-layout>
