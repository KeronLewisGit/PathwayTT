<div class="space-y-6" @if ($hasProfile && $lastComputed === null) wire:poll.4s @endif>
    {{-- Status bar --}}
    <div class="bg-white shadow sm:rounded-lg p-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <div class="text-gray-600">
            @if ($lastComputed)
                {{ $matches->total() }} eligible {{ Str::plural('match', $matches->total()) }}
                @if ($ineligibleCount) · {{ $ineligibleCount }} not eligible from T&amp;T @endif
                · computed {{ $lastComputed->diffForHumans() }}
            @elseif ($hasProfile)
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    Computing your matches…
                </span>
            @else
                No profile yet.
            @endif

            @if (session('matches-recompute'))
                <span class="ml-2 font-medium text-green-600">{{ session('matches-recompute') }}</span>
            @endif
        </div>

        <div class="flex items-center gap-4">
            @if ($ineligibleCount)
                <label class="flex items-center gap-2 text-gray-700">
                    <input type="checkbox" wire:model.live="showIneligible" class="rounded border-gray-300 text-gray-800 shadow-sm" />
                    Show ineligible
                </label>
            @endif
            <button type="button" wire:click="recompute" class="text-gray-600 hover:text-gray-900">Recompute</button>
            <a href="{{ route('preferences.index') }}" class="text-indigo-600 hover:text-indigo-500">Edit preferences</a>
        </div>
    </div>

    {{-- Onboarding nudges --}}
    @unless ($hasProfile)
        <div class="rounded-md bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
            <strong>Start with your resume.</strong>
            <a href="{{ route('resume.index') }}" class="underline">Upload it</a> or
            <a href="{{ route('profile.review') }}" class="underline">fill in your profile</a>
            and we'll score every open listing for you.
        </div>
    @endunless

    @if ($hasProfile && ! $hasPreferences)
        <div class="rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
            <a href="{{ route('preferences.index') }}" class="underline font-medium">Set your job preferences</a>
            — target industry and work arrangement count for 20% of every score.
        </div>
    @endif

    {{-- Advisory pivot: never an empty results page with no next action --}}
    @if ($advisory && $lastComputed)
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 text-sm text-indigo-950">
            <h3 class="text-base font-semibold">
                @if ($matches->total() === 0)
                    No eligible listings match your profile yet
                @else
                    Your best match scores {{ $best }} — below the {{ $threshold }} threshold we use for a confident fit
                @endif
            </h3>

            @if ($topGaps->isNotEmpty())
                <p class="mt-2">These required skills are the ones most often standing between you and the listings you're eligible for:</p>
                <ul class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                    @foreach ($topGaps as $gap)
                        <li class="flex items-center justify-between rounded bg-white/70 px-3 py-1.5">
                            <span class="font-medium">{{ $gap['name'] }}</span>
                            <span class="text-xs text-indigo-700">required by {{ $gap['jobs'] }} {{ Str::plural('listing', $gap['jobs']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-3">
                Already have one of these? <a href="{{ route('profile.review') }}" class="underline font-medium">Add it to your profile</a> and recompute.
                Your personalised Skills Gap Plan — local T&amp;T and online options ranked by impact — is coming in the next release.
            </p>
        </div>
    @endif

    {{-- Ranked matches --}}
    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-100">
        @forelse ($matches as $match)
            @php
                $job = $match->jobListing;
                $breakdown = $match->score_breakdown ?? [];
                $missingRequired = collect($match->missing_skills ?? [])->where('required', true);
                $missingPreferred = collect($match->missing_skills ?? [])->where('required', false);
                $tone = $match->score >= 75 ? 'bg-green-100 text-green-800' : ($match->score >= $threshold ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700');
            @endphp

            <article class="p-4 sm:p-5" x-data="{ open: false }">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div class="shrink-0">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold {{ $tone }}" title="Match score">
                            {{ $match->score }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-gray-900">
                            <a href="{{ route('jobs.show', $job) }}" class="hover:underline">{{ $job->title }}</a>
                        </h3>
                        <p class="text-sm text-gray-600">
                            {{ $job->company_name ?: 'Company not stated' }}
                            @if ($job->location_text) · {{ $job->location_text }} @endif
                            @if ($job->industry) · {{ $job->industry->name }} @endif
                        </p>

                        <x-job-badges :job="$job" class="mt-2" />

                        @if ($label = $salary->format($job))
                            <p class="mt-2 text-sm text-gray-800">{{ $label }}</p>
                        @endif

                        <p class="mt-2 text-sm text-gray-700">{{ $breakdown['summary'] ?? '' }}</p>

                        @if ($missingRequired->isNotEmpty() || $missingPreferred->isNotEmpty() || ! empty($breakdown['gaps']))
                            <div class="mt-2 text-sm">
                                <p class="font-medium text-gray-800">What you're missing</p>
                                <ul class="mt-1 space-y-0.5 text-gray-700">
                                    @foreach ($missingRequired as $skill)
                                        <li><span class="inline-block rounded bg-red-50 px-1.5 text-xs font-medium text-red-800">required</span> {{ $skill['name'] }}</li>
                                    @endforeach
                                    @foreach ($missingPreferred as $skill)
                                        <li><span class="inline-block rounded bg-gray-100 px-1.5 text-xs font-medium text-gray-600">nice to have</span> {{ $skill['name'] }}</li>
                                    @endforeach
                                    @foreach ($breakdown['gaps'] ?? [] as $gap)
                                        <li>{{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <button type="button" @click="open = !open" class="text-sm text-indigo-600 hover:text-indigo-500">
                                <span x-text="open ? 'Hide breakdown' : 'Why this score?'"></span>
                            </button>
                            @livewire(App\Livewire\JobTrackButton::class, ['jobListingId' => $job->id], key('track-'.$job->id))
                        </div>

                        <div x-show="open" x-cloak class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                            <x-match-breakdown :match="$match" />
                        </div>
                    </div>

                    <div class="shrink-0 text-xs text-gray-500 sm:text-right">
                        @if ($job->posted_at)
                            <div>Posted {{ $job->posted_at->timezone(config('app.display_timezone'))->format('d M Y') }}</div>
                        @endif
                        <a href="{{ route('jobs.show', $job) }}" class="mt-1 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">Details →</a>
                    </div>
                </div>
            </article>
        @empty
            @if ($lastComputed)
                <p class="p-6 text-sm text-gray-500">No eligible listings right now. New jobs are checked nightly; you'll see them here as soon as they land.</p>
            @endif
        @endforelse
    </div>

    <div>{{ $matches->links() }}</div>

    {{-- Ineligible (hard-filtered) listings, on request --}}
    @if ($showIneligible && $ineligible->isNotEmpty())
        <div class="bg-white shadow sm:rounded-lg">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Not eligible from Trinidad &amp; Tobago</h3>
                <p class="text-xs text-gray-500">These fail a hard rule (location restriction, work permit, closed) so they aren't scored.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($ineligible as $match)
                    <li class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('jobs.show', $match->jobListing) }}" class="font-medium text-gray-900 hover:underline">{{ $match->jobListing->title }}</a>
                            <span class="text-gray-500">· {{ $match->jobListing->company_name ?: 'Company not stated' }}</span>
                        </div>
                        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ $match->ineligibility_reason }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
