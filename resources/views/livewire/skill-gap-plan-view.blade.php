<div class="space-y-6" @if ($generating) wire:poll.4s @endif>
    {{-- Status --}}
    <div class="card p-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <div class="text-gray-600">
            @if ($generating)
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    Building your plan from today's listings…
                </span>
            @elseif ($plan)
                Plan generated {{ $plan->generated_at->diffForHumans() }}
                · {{ $payload['scope']['listings_considered'] ?? 0 }} listings considered
                @if (! empty($payload['scope']['industry']))
                    in {{ $payload['scope']['industry'] }}
                @endif
                @if (! empty($payload['scope']['widened']))
                    <span class="text-amber-700">(too few in your preferred scope, so all open listings were used)</span>
                @endif
            @elseif ($generateError)
                <span class="text-red-700">{{ $generateError }}</span>
            @elseif (! $hasProfile)
                No profile yet.
            @else
                No plan yet.
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="button" wire:click="generate" wire:loading.attr="disabled" @if ($generating) disabled @endif class="btn-secondary btn-sm">
                <span wire:loading.remove wire:target="generate">{{ $plan ? 'Regenerate' : 'Generate plan' }}</span>
                <span wire:loading wire:target="generate">Building…</span>
            </button>
            @if ($plan)
                <a href="{{ route('plan.pdf') }}" class="link">Download PDF</a>
            @endif
            <a href="{{ route('matches.index') }}" class="link">Back to matches</a>
        </div>
    </div>

    @unless ($hasProfile)
        <div class="callout-warning">
            <strong>Start with your resume.</strong>
            <a href="{{ route('resume.index') }}" class="underline">Upload it</a> or
            <a href="{{ route('profile.review') }}" class="underline">fill in your profile</a>
            so we know which skills you already have.
        </div>
    @endunless

    @if ($plan && ! $generating)
        {{-- Standing then vs now --}}
        @php
            $then = (int) ($payload['current']['best_score'] ?? 0);
            $threshold = (int) ($payload['threshold'] ?? 55);
            $closed = collect($payload['gaps'] ?? [])->filter(fn ($g) => in_array($g['skill']['id'], $doneSkillIds, true));
        @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Best match when planned</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $then }}<span class="text-sm text-gray-400"> / 100</span></p>
                <p class="text-xs text-gray-500">{{ $payload['current']['above_threshold'] ?? 0 }} of {{ $payload['current']['eligible_count'] ?? 0 }} eligible listings at or above {{ $threshold }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Best match now</p>
                <p class="mt-1 text-2xl font-semibold {{ $currentBest > $then ? 'text-green-700' : 'text-gray-900' }}">{{ $currentBest }}<span class="text-sm text-gray-400"> / 100</span></p>
                @if ($currentBest > $then)
                    <p class="text-xs text-green-700">Up {{ $currentBest - $then }} since this plan</p>
                @else
                    <p class="text-xs text-gray-500">Recomputed as you update your profile</p>
                @endif
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">Gaps closed</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $closed->count() }}<span class="text-sm text-gray-400"> / {{ count($payload['gaps'] ?? []) }}</span></p>
                <p class="text-xs text-gray-500">Skills from this plan now on your profile</p>
            </div>
        </div>

        @if (empty($payload['gaps']))
            <div class="callout-success">
                @if (($payload['current']['eligible_count'] ?? 0) === 0)
                    There are no open listings in your scope to plan against yet. New jobs are checked nightly.
                @else
                    You already hold every skill the listings in your scope ask for. Check the advice below and your <a href="{{ route('matches.index') }}" class="underline">matches</a>.
                @endif
            </div>
        @endif

        {{-- Three phases --}}
        @foreach ($payload['phases'] ?? [] as $phase)
            <section class="card">
                <div class="px-5 py-4 border-b border-gray-100 flex items-baseline justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900">{{ $phase['title'] }}</h3>
                    <span class="text-xs text-gray-500">{{ $phase['window'] }} · {{ count($phase['items']) }} {{ Str::plural('item', count($phase['items'])) }}</span>
                </div>

                @if ($phase['items'] === [])
                    <p class="px-5 py-4 text-sm text-gray-500">Nothing in this window right now.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($phase['items'] as $item)
                            @php $done = in_array($item['skill']['id'], $doneSkillIds, true); @endphp
                            <li class="p-5" x-data="{ open: false }">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-700 text-xs font-semibold text-white">{{ $item['rank'] }}</span>
                                            <h4 class="text-base font-semibold text-gray-900 {{ $done ? 'line-through text-gray-400' : '' }}">{{ $item['skill']['name'] }}</h4>
                                            @if ($done)
                                                <span class="badge-success">Done — on your profile</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-700">
                                            Required by {{ $item['jobs_requiring'] }} {{ Str::plural('listing', $item['jobs_requiring']) }}@if ($item['jobs_preferring']), nice-to-have in {{ $item['jobs_preferring'] }}@endif.
                                            Adding it would
                                            @if ($item['jobs_unlocked'] > 0)
                                                <strong>unlock {{ $item['jobs_unlocked'] }} {{ Str::plural('listing', $item['jobs_unlocked']) }}</strong> (score crosses {{ $threshold }}) and
                                            @endif
                                            lift your score by about <strong>+{{ $item['avg_lift'] }}</strong> on the listings that ask for it, best would become <strong>{{ $item['best_after'] }}</strong>.
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-xs text-gray-500 sm:text-right">
                                        <div>Effort ~{{ $item['effort_weeks'] }} {{ Str::plural('week', $item['effort_weeks']) }}@if ($item['effort_estimated']) (estimate)@endif</div>
                                        <div>Impact {{ $item['impact_per_week'] }} / week</div>
                                    </div>
                                </div>

                                @php
                                    $hasResources = $item['resources']['local'] !== [] || $item['resources']['online'] !== [];
                                    $extra = max(count($item['resources']['local']) - 1, 0) + max(count($item['resources']['online']) - 1, 0);
                                @endphp
                                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    @foreach (['local' => 'Locally in Trinidad & Tobago', 'online' => 'Online / international'] as $track => $heading)
                                        <div>
                                            <p class="eyebrow">{{ $heading }}</p>
                                            @forelse ($item['resources'][$track] as $i => $resource)
                                                <div class="mt-2" @if ($i > 0) x-show="open" x-cloak @endif>
                                                    <x-learning-resource-card :resource="$resource" />
                                                </div>
                                            @empty
                                                <p class="mt-2 text-sm text-gray-500">No {{ $track }} provider catalogued for this skill yet.</p>
                                            @endforelse
                                        </div>
                                    @endforeach
                                </div>

                                @if ($extra > 0)
                                    <button type="button" @click="open = !open" class="mt-2 text-sm link" x-text="open ? 'Show fewer providers' : 'Show {{ $extra }} more {{ Str::plural('provider', $extra) }}'"></button>
                                @endif

                                @unless ($hasResources)
                                    <p class="mt-2 text-xs text-gray-500">Effort shown is a default estimate because nothing is catalogued for this skill yet.</p>
                                @endunless

                                @unless ($done)
                                    <p class="mt-3 text-xs text-gray-500">
                                        Already have this skill? <a href="{{ route('profile.review') }}" class="link">Add it to your profile</a> and your matches recompute automatically.
                                    </p>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        {{-- Non-credential advice --}}
        @if (! empty($payload['advice']))
            <section class="card">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">Beyond courses</h3>
                    <p class="text-xs text-gray-500">Suggested because the listings in your scope call for it.</p>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($payload['advice'] as $tip)
                        <li class="p-5">
                            <p class="text-sm font-semibold text-gray-900">{{ $tip['title'] }}</p>
                            <p class="mt-1 text-sm text-gray-700">{{ $tip['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- History --}}
        @if ($history->count() > 1)
            <section class="card p-5 text-sm">
                <h3 class="text-sm font-semibold text-gray-900">Previous plans</h3>
                <ul class="mt-2 space-y-1 text-gray-600">
                    @foreach ($history as $past)
                        <li>
                            {{ $past->generated_at->timezone(config('app.display_timezone'))->format('d M Y H:i') }}
                            · best match {{ $past->payload['current']['best_score'] ?? 0 }}
                            · {{ count($past->payload['gaps'] ?? []) }} gaps
                            @if ($past->id === $plan->id) <span class="text-gray-400">(current)</span> @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
