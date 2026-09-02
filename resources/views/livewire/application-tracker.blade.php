<div class="space-y-6">
    {{-- Pipeline + weekly goal --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <p class="eyebrow">Your pipeline</p>
            <ol class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
                @foreach ($statuses as $stage)
                    @php $n = (int) ($counts[$stage->value] ?? 0); @endphp
                    <li class="rounded-md border p-3 text-center {{ $n > 0 ? 'border-brand-200 bg-brand-50' : 'border-gray-200' }}">
                        <p class="text-2xl font-semibold {{ $n > 0 ? 'text-brand-800' : 'text-gray-400' }}">{{ $n }}</p>
                        <p class="text-xs text-gray-600">{{ $stage->label() }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="card p-5">
            <p class="eyebrow">This week's goal</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $weekly['count'] }}<span class="text-base font-normal text-gray-400"> / {{ $weekly['goal'] }} applied</span></p>
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                <div class="h-2 rounded-full {{ $weekly['met'] ? 'bg-green-500' : 'bg-brand-500' }}" style="width: {{ $weekly['progress'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                @if ($weekly['met'])
                    Goal met — momentum like this is what gets interviews.
                @elseif ($weekly['count'] > 0)
                    {{ $weekly['goal'] - $weekly['count'] }} more to hit this week's goal.
                @else
                    Aim for {{ $weekly['goal'] }} applications a week; small steady steps beat bursts.
                @endif
            </p>
        </div>
    </div>

    {{-- Status tabs --}}
    <div class="card p-3 flex flex-wrap items-center gap-2 text-sm">
        <button type="button" wire:click="$set('status', '')"
                class="rounded-full px-3 py-1 {{ $status === '' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
            All ({{ $counts->sum() }})
        </button>
        @foreach ($statuses as $option)
            <button type="button" wire:click="$set('status', '{{ $option->value }}')"
                    class="rounded-full px-3 py-1 {{ $status === $option->value ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                {{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})
            </button>
        @endforeach
        <a href="{{ route('matches.index') }}" class="ml-auto link">Find more matches →</a>
    </div>

    <div class="card divide-y divide-gray-100">
        @forelse ($applications as $application)
            @php
                $job = $application->jobListing;
                $tone = match ($application->status) {
                    App\Enums\ApplicationStatus::Offer => 'bg-green-100 text-green-800',
                    App\Enums\ApplicationStatus::Rejected => 'bg-red-100 text-red-800',
                    App\Enums\ApplicationStatus::Interviewing => 'bg-blue-100 text-blue-800',
                    App\Enums\ApplicationStatus::Applied => 'bg-amber-100 text-amber-800',
                    default => 'bg-gray-100 text-gray-700',
                };
            @endphp

            <article class="p-4 sm:p-5 space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900">
                            <a href="{{ route('jobs.show', $job) }}" class="hover:underline">{{ $job->title }}</a>
                        </h3>
                        <p class="text-sm text-gray-600">
                            {{ $job->company_name ?: 'Company not stated' }}
                            @if ($job->location_text) · {{ $job->location_text }} @endif
                        </p>
                        <x-job-badges :job="$job" class="mt-2" />
                    </div>

                    <div class="shrink-0 flex flex-col items-start gap-1 sm:items-end text-xs text-gray-500">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone }}">{{ $application->status->label() }}</span>
                        @if ($application->applied_at)
                            <span>Applied {{ $application->applied_at->timezone(config('app.display_timezone'))->format('d M Y') }}</span>
                        @endif
                        <span>Updated {{ $application->updated_at->diffForHumans() }}</span>
                        @if ($job->apply_url)
                            <a href="{{ $job->apply_url }}" target="_blank" rel="noopener noreferrer" class="link">Open posting ↗</a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    @foreach ($application->status->nextStatuses() as $next)
                        <button type="button" wire:click="setStatus({{ $application->id }}, '{{ $next->value }}')"
                                class="btn-secondary btn-sm">
                            Mark {{ strtolower($next->label()) }}
                        </button>
                    @endforeach
                    @if ($application->status->nextStatuses() === [])
                        <span class="text-xs text-gray-500">Final status</span>
                    @endif
                    <button type="button" wire:click="remove({{ $application->id }})" wire:confirm="Remove this job from your tracker?"
                            class="text-xs text-gray-500 hover:text-red-600">Remove</button>

                    @error("status.{$application->id}")
                        <span class="text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="notes-{{ $application->id }}" class="text-xs font-medium text-gray-600">Notes</label>
                    <textarea id="notes-{{ $application->id }}" rows="2"
                              wire:model="notes.{{ $application->id }}"
                              wire:blur="saveNotes({{ $application->id }})"
                              placeholder="Contact name, interview date, what to prepare…"
                              class="mt-1 form-control text-sm"></textarea>
                    @error("notes.{$application->id}")
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @if (session("notes-saved-{$application->id}"))
                        <x-flash :message="session("notes-saved-{$application->id}")" class="text-xs" />
                    @endif
                </div>
            </article>
        @empty
            <div class="p-8 text-center text-sm text-gray-500">
                <p class="font-medium text-gray-900">Nothing tracked yet.</p>
                <p class="mt-1">Save a job from <a href="{{ route('matches.index') }}" class="link">your matches</a> or the <a href="{{ route('jobs.index') }}" class="link">job list</a> to track it here.</p>
            </div>
        @endforelse
    </div>
</div>
