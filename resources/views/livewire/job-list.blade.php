<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white shadow sm:rounded-lg p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="search" class="sr-only">Search</label>
                <x-text-input id="search" wire:model.live.debounce.400ms="search" placeholder="Search title, company or location" class="block w-full" />
            </div>

            <select wire:model.live="industry" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">All industries</option>
                @foreach ($this->industries as $industry)
                    <option value="{{ $industry->id }}">{{ $industry->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="arrangement" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">Any arrangement</option>
                @foreach ($arrangements as $arrangement)
                    <option value="{{ $arrangement->value }}">{{ $arrangement->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="employment" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">Any employment type</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex items-center gap-2 text-gray-700">
                <input type="checkbox" wire:model.live="eligibleOnly" class="rounded border-gray-300 text-gray-800 shadow-sm" />
                Only show jobs open to Trinidad &amp; Tobago residents
            </label>

            <div class="flex items-center gap-4">
                <span class="text-gray-500">{{ $jobs->total() }} {{ Str::plural('listing', $jobs->total()) }}</span>
                <button type="button" wire:click="clearFilters" class="text-gray-600 hover:text-gray-900">Clear filters</button>
                <a href="{{ route('preferences.index') }}" class="text-indigo-600 hover:text-indigo-500">Edit preferences</a>
            </div>
        </div>
    </div>

    {{-- Results --}}
    <div class="bg-white shadow sm:rounded-lg divide-y divide-gray-100" wire:loading.class="opacity-50">
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
                    <span class="uppercase tracking-wide">via {{ $job->source }}</span>
                    <a href="{{ route('jobs.show', $job) }}" class="mt-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">Details →</a>
                </div>
            </article>
        @empty
            <div class="p-8 text-center">
                <p class="text-sm font-medium text-gray-900">No listings match these filters.</p>
                <p class="mt-1 text-sm text-gray-500">
                    Try clearing a filter, or
                    <a href="{{ route('profile.review') }}" class="text-indigo-600 hover:text-indigo-500">complete your profile</a>
                    so we can suggest skills that would open up more roles.
                </p>
            </div>
        @endforelse
    </div>

    <div>{{ $jobs->links() }}</div>
</div>
