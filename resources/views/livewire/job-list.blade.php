<div class="space-y-6" wire:poll.60s>
    {{-- Live feed status --}}
    <div class="card px-4 py-3 flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2 text-gray-700">
            <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-60"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
            </span>
            <span>
                <strong>Live feed</strong> · {{ number_format($feed['total']) }} open {{ Str::plural('listing', $feed['total']) }}
                @if ($feed['updated_at'])
                    · updated {{ $feed['updated_at']->diffForHumans() }}
                @else
                    · first fetch in progress
                @endif
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
            @foreach ($feed['sources'] as $source)
                <span class="badge-neutral">{{ $source['label'] }} · {{ $source['count'] }}</span>
            @endforeach
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="search" class="sr-only">Search</label>
                <x-text-input id="search" wire:model.live.debounce.400ms="search" placeholder="Search title, company or location" class="block w-full" />
            </div>

            <select wire:model.live="location" class="form-control" aria-label="Location">
                <option value="">Any location</option>
                @foreach ($this->locations as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="industry" class="form-control" aria-label="Industry">
                <option value="">All industries</option>
                @foreach ($this->industries as $industry)
                    <option value="{{ $industry->id }}">{{ $industry->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="arrangement" class="form-control">
                <option value="">Any arrangement</option>
                @foreach ($arrangements as $arrangement)
                    <option value="{{ $arrangement->value }}">{{ $arrangement->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="employment" class="form-control">
                <option value="">Any employment type</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex items-center gap-2 text-gray-700">
                <input type="checkbox" wire:model.live="eligibleOnly" class="form-check" />
                Only show jobs open to Trinidad &amp; Tobago residents
            </label>

            <div class="flex items-center gap-4">
                <span class="text-gray-500">{{ $jobs->total() }} {{ Str::plural('listing', $jobs->total()) }}</span>
                <button type="button" wire:click="clearFilters" class="btn-secondary btn-sm">Clear filters</button>
                <a href="{{ route('preferences.index') }}" class="link">Edit preferences</a>
            </div>
        </div>
    </div>

    {{-- Results --}}
    <div class="card divide-y divide-gray-100" wire:loading.class="opacity-50">
        @forelse ($jobs as $job)
            <article class="p-4 sm:p-5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
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
                </div>

                <div class="shrink-0 flex flex-col items-start sm:items-end gap-1 text-xs text-gray-500">
                    @if ($job->posted_at)
                        <span>Posted {{ $job->posted_at->timezone(config('app.display_timezone'))->format('d M Y') }}</span>
                    @endif
                    @if ($job->closes_at)
                        <span>Closes {{ $job->closes_at->timezone(config('app.display_timezone'))->format('d M Y') }}</span>
                    @endif
                    <x-job-source :job="$job" />
                    <a href="{{ route('jobs.show', $job) }}" class="mt-1 text-sm font-medium link">Details →</a>
                </div>
            </article>
        @empty
            <div class="p-8 text-center">
                <p class="text-sm font-medium text-gray-900">No listings match these filters.</p>
                <p class="mt-1 text-sm text-gray-500">
                    Try clearing a filter, or
                    <a href="{{ route('profile.review') }}" class="link">complete your profile</a>
                    so we can suggest skills that would open up more roles.
                </p>
            </div>
        @endforelse
    </div>

    <div>{{ $jobs->links() }}</div>
</div>
