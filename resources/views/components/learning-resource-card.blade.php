@props(['resource'])

<div class="rounded-md border border-gray-200 bg-white p-3 text-sm">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="font-medium text-gray-900">{{ $resource['provider'] }}</p>
            <p class="text-gray-700">{{ $resource['title'] }}</p>
        </div>
        @if ($resource['url'])
            <a href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-xs font-medium link">Visit ↗</a>
        @endif
    </div>

    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-600">
        <div>
            <dt class="inline text-gray-400">Format:</dt>
            <dd class="inline">{{ $resource['delivery_mode'] ? str_replace('_', ' ', $resource['delivery_mode']) : 'not stated' }}</dd>
        </div>
        <div>
            <dt class="inline text-gray-400">Credential:</dt>
            <dd class="inline">{{ $resource['credential_type'] ?: 'not stated' }}</dd>
        </div>
        <div>
            <dt class="inline text-gray-400">Duration:</dt>
            <dd class="inline">
                @if ($resource['duration_weeks'])
                    {{ $resource['duration_weeks'] }} {{ Str::plural('week', $resource['duration_weeks']) }}
                @else
                    ~{{ $resource['effort_weeks'] }} weeks (estimate)
                @endif
            </dd>
        </div>
        <div>
            <dt class="inline text-gray-400">Cost:</dt>
            <dd class="inline">{{ $resource['cost'] ?: ($resource['cost_note'] ?: 'not listed') }}</dd>
        </div>
    </dl>

    @if ($resource['cost'] === null && $resource['duration_weeks'] === null)
        <p class="mt-2 text-xs text-gray-500">Contact provider for current course listing, dates and fees.</p>
    @elseif ($resource['cost'] !== null && $resource['cost_note'])
        <p class="mt-2 text-xs text-gray-500">{{ $resource['cost_note'] }}</p>
    @endif

    @if ($resource['notes'])
        <p class="mt-1 text-xs text-gray-500">{{ $resource['notes'] }}</p>
    @endif
</div>
