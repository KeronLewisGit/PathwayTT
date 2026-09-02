<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">{{ $job->title }}</h2>
            <a href="{{ route('jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-900 shrink-0">← All jobs</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($reason = $job->ineligibilityReason())
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                    <strong>Not eligible from Trinidad &amp; Tobago:</strong> {{ $reason }}.
                </div>
            @endif

            @unless ($job->isOpen())
                <div class="rounded-md bg-gray-100 border border-gray-200 p-4 text-sm text-gray-700">
                    This listing is closed. It is shown for reference only.
                </div>
            @endunless

            {{-- Your match --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h3 class="text-lg font-medium text-gray-900">Your match</h3>
                    @if ($match && $match->is_eligible)
                        @php $tone = $match->score >= 75 ? 'bg-green-100 text-green-800' : ($match->score >= 55 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700'); @endphp
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full text-base font-bold {{ $tone }}">{{ $match->score }}</span>
                    @endif
                </div>

                @if ($match === null)
                    <p class="mt-2 text-sm text-gray-600">
                        Not scored yet.
                        <a href="{{ route('matches.index') }}" class="text-indigo-600 hover:text-indigo-500">Open your matches</a>
                        to compute scores for every open listing.
                    </p>
                @elseif (! $match->is_eligible)
                    <p class="mt-2 text-sm text-red-800">Not eligible: {{ $match->ineligibility_reason }}.</p>
                @else
                    <p class="mt-2 text-sm text-gray-700">{{ $match->score_breakdown['summary'] ?? '' }}</p>

                    @php $missing = collect($match->missing_skills ?? []); @endphp
                    @if ($missing->isNotEmpty() || ! empty($match->score_breakdown['gaps']))
                        <p class="mt-3 text-sm font-medium text-gray-800">What you're missing</p>
                        <ul class="mt-1 space-y-0.5 text-sm text-gray-700">
                            @foreach ($missing as $skill)
                                <li>
                                    <span class="inline-block rounded px-1.5 text-xs font-medium {{ $skill['required'] ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-600' }}">{{ $skill['required'] ? 'required' : 'nice to have' }}</span>
                                    {{ $skill['name'] }}
                                </li>
                            @endforeach
                            @foreach ($match->score_breakdown['gaps'] ?? [] as $gap)
                                <li>{{ $gap }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm text-indigo-600 hover:text-indigo-500">Why this score?</summary>
                        <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                            <x-match-breakdown :match="$match" />
                        </div>
                    </details>
                @endif

                <div class="mt-4">
                    @livewire(App\Livewire\JobTrackButton::class, ['jobListingId' => $job->id], key('track-'.$job->id))
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ $job->company_name ?: 'Company not stated' }}
                    @if ($job->location_text) · {{ $job->location_text }} @endif
                    @if ($job->industry) · {{ $job->industry->name }} @endif
                </p>

                <x-job-badges :job="$job" class="mt-3" />

                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    @if ($salaryLabel)
                        <div>
                            <dt class="text-gray-500">Salary</dt>
                            <dd class="text-gray-900">{{ $salaryLabel }}</dd>
                        </div>
                    @endif

                    @if ($job->work_arrangement === App\Enums\WorkArrangement::RemoteInternational)
                        <div>
                            <dt class="text-gray-500">Geographic eligibility</dt>
                            <dd class="text-gray-900">
                                {{ $job->geo_eligibility?->label() ?? 'Not stated' }}
                                @if ($job->country) (employer in {{ $job->country }}) @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Timezone</dt>
                            <dd class="text-gray-900">
                                T&amp;T is AST (UTC-4) with no daylight saving, so it matches US Eastern from March to November.
                                @if ($job->required_overlap_hours)
                                    This role asks for {{ $job->required_overlap_hours }} hours of overlap with the employer's day.
                                @endif
                            </dd>
                        </div>
                    @endif

                    @if ($job->posted_at)
                        <div>
                            <dt class="text-gray-500">Posted</dt>
                            <dd class="text-gray-900">{{ $job->posted_at->timezone(config('app.display_timezone'))->format('d M Y') }}</dd>
                        </div>
                    @endif

                    @if ($job->closes_at)
                        <div>
                            <dt class="text-gray-500">Closes</dt>
                            <dd class="text-gray-900">{{ $job->closes_at->timezone(config('app.display_timezone'))->format('d M Y') }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-gray-500">Source</dt>
                        <dd class="text-gray-900 uppercase">{{ $job->source }}</dd>
                    </div>
                </dl>

                <div class="mt-6">
                    @if ($job->apply_url)
                        <a href="{{ $job->apply_url }}" target="_blank" rel="noopener noreferrer nofollow"
                           class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                            Apply on original posting ↗
                        </a>
                        <p class="mt-2 text-xs text-gray-500">Applications happen on the employer's site, not here.</p>
                    @else
                        <p class="text-sm text-gray-500">No application link was provided for this listing.</p>
                    @endif
                </div>
            </div>

            @if ($requiredSkills->isNotEmpty() || $preferredSkills->isNotEmpty())
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">Skills</h3>

                    @if ($requiredSkills->isNotEmpty())
                        <p class="mt-3 text-sm font-medium text-gray-700">Required</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($requiredSkills as $skill)
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($preferredSkills->isNotEmpty())
                        <p class="mt-3 text-sm font-medium text-gray-700">Nice to have</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($preferredSkills as $skill)
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($job->requirements)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">Requirements</h3>
                    <ul class="mt-3 list-disc list-inside space-y-1 text-sm text-gray-800">
                        @foreach ($job->requirements as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($job->description)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">Description</h3>
                    <div class="mt-3 text-sm text-gray-800 whitespace-pre-line">{{ $job->description }}</div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
