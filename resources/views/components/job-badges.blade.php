@props(['job'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
    <span class="badge-neutral">
        {{ $job->work_arrangement?->label() }}
    </span>

    @if ($job->employment_type)
        <span class="badge-neutral">
            {{ $job->employment_type->label() }}
        </span>
    @endif

    @if ($job->seniority)
        <span class="badge-neutral">
            {{ App\Models\JobListing::SENIORITIES[$job->seniority] ?? ucfirst($job->seniority) }}
        </span>
    @endif

    @if ($job->work_arrangement === App\Enums\WorkArrangement::RemoteInternational && $job->required_overlap_hours)
        <span class="badge-info">
            {{ $job->required_overlap_hours }}h overlap required
        </span>
    @endif

    @if ($reason = $job->ineligibilityReason())
        <span class="badge-danger" title="{{ $reason }}">
            Not eligible from T&amp;T
        </span>
    @elseif ($job->geo_eligibility === App\Enums\GeoEligibility::Worldwide && $job->work_arrangement === App\Enums\WorkArrangement::RemoteInternational)
        <span class="badge-success">
            Hires worldwide
        </span>
    @endif

    @unless ($job->isOpen())
        <span class="badge bg-gray-800 text-white">Closed</span>
    @endunless
</div>
