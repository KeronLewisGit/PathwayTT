<div class="flex flex-wrap items-center gap-2 text-sm">
    @if ($application === null)
        <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="btn-secondary btn-sm">
            ☆ Save job
        </button>
    @else
        @php
            $tone = match ($application->status) {
                App\Enums\ApplicationStatus::Offer => 'bg-green-100 text-green-800',
                App\Enums\ApplicationStatus::Rejected => 'bg-red-100 text-red-800',
                App\Enums\ApplicationStatus::Interviewing => 'bg-blue-100 text-blue-800',
                App\Enums\ApplicationStatus::Applied => 'bg-amber-100 text-amber-800',
                default => 'bg-gray-100 text-gray-700',
            };
        @endphp

        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone }}">{{ $application->status->label() }}</span>

        @foreach ($application->status->nextStatuses() as $next)
            <button type="button" wire:click="setStatus('{{ $next->value }}')" wire:loading.attr="disabled"
                    class="text-xs font-medium link">
                Mark {{ strtolower($next->label()) }}
            </button>
        @endforeach

        <button type="button" wire:click="remove" wire:confirm="Remove this job from your tracker?"
                class="text-xs text-gray-500 hover:text-red-600">
            Remove
        </button>

        @error('status')
            <span class="text-xs text-red-600">{{ $message }}</span>
        @enderror
    @endif
</div>
