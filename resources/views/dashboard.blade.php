<x-app-layout title="Dashboard">
    <x-slot name="header"><x-page-title title="Dashboard" /></x-slot>

    <x-page width="wide">
            {{-- Next step --}}
            <div class="card p-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="eyebrow">Next step</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $nextStep['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $nextStep['body'] }}</p>
                </div>
                <a href="{{ route($nextStep['route']) }}" class="btn-primary shrink-0">{{ $nextStep['cta'] }} →</a>
            </div>

            {{-- Setup progress --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ([
                    ['key' => 'resume', 'label' => 'Resume', 'route' => 'resume.index',
                        'detail' => $resume ? ($resumeProcessing ? 'Parsing…' : 'Uploaded '.$resume->created_at->diffForHumans()) : 'Not uploaded yet'],
                    ['key' => 'profile', 'label' => 'Profile', 'route' => 'profile.review',
                        'detail' => $profile ? "{$skillCount} ".Str::plural('skill', $skillCount).' on record' : 'Not started'],
                    ['key' => 'preferences', 'label' => 'Preferences', 'route' => 'preferences.index',
                        'detail' => $preference ? ($preference->industry?->name ?? 'Any industry').' · '.(count($preference->work_arrangements ?? []) ?: 'any').' arrangement(s)' : 'Not set'],
                ] as $i => $step)
                    <a href="{{ route($step['route']) }}" class="card p-4 flex items-center gap-3 hover:ring-brand-300">
                        <span class="score h-9 w-9 text-sm {{ $steps[$step['key']] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                            @if ($steps[$step['key']]) ✓ @else {{ $i + 1 }} @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900">{{ $step['label'] }}</span>
                            <span class="block truncate text-xs text-gray-500">{{ $step['detail'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Live status --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('matches.index') }}" class="card p-5 hover:ring-brand-300">
                    <p class="eyebrow">Best match</p>
                    <p class="mt-2 text-3xl font-semibold {{ $best >= $threshold ? 'text-green-700' : 'text-gray-900' }}">{{ $best }}<span class="text-base font-normal text-gray-400"> / 100</span></p>
                    <p class="mt-1 text-xs text-gray-500">
                        @if ($lastComputed)
                            {{ $aboveThreshold }} of {{ $eligibleCount }} eligible listings at or above {{ $threshold }}
                        @else
                            Not computed yet
                        @endif
                    </p>
                </a>

                <a href="{{ route('matches.index') }}" class="card p-5 hover:ring-brand-300">
                    <p class="eyebrow">Eligible listings</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $eligibleCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">Open jobs you can apply to from T&amp;T</p>
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

            <p class="text-xs text-gray-500">
                Match scores recompute automatically when you edit your profile or preferences, and nightly as new listings arrive.
            </p>
    </x-page>
</x-app-layout>
