@props(['job'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
        {{ $job->work_arrangement?->label() }}
    </span>

    @if ($job->employment_type)
        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
            {{ $job->employment_type->label() }}
        </span>
    @endif

    @if ($job->seniority)
        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
            {{ App\Models\JobListing::SENIORITIES[$job->seniority] ?? ucfirst($job->seniority) }}
        </span>
    @endif

    @if ($job->work_arrangement === App\Enums\WorkArrangement::RemoteInternational && $job->required_overlap_hours)
        <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
            {{ $job->required_overlap_hours }}h overlap required
        </span>
    @endif

    @if ($reason = $job->ineligibilityReason())
        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800" title="{{ $reason }}">
            Not eligible from T&amp;T
        </span>
    @elseif ($job->geo_eligibility === App\Enums\GeoEligibility::Worldwide && $job->work_arrangement === App\Enums\WorkArrangement::RemoteInternational)
        <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
            Hires worldwide
        </span>
    @endif

    @unless ($job->isOpen())
        <span class="inline-flex rounded-full bg-gray-800 px-2 py-0.5 text-xs font-medium text-white">Closed</span>
    @endunless
</div>
