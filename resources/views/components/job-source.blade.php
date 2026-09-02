@props(['job'])

@php
    $meta = config("jobsources.labels.{$job->source}") ?? ['label' => ucfirst($job->source), 'url' => null];
    $href = $job->apply_url ?: ($meta['url'] ?? null);
@endphp

{{-- Attribution: several boards require naming them and a (follow) link to the original posting. --}}
<span {{ $attributes->merge(['class' => 'text-xs text-gray-500']) }}>
    Source:
    @if ($href)
        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="link">{{ $meta['label'] }}</a>
    @else
        <span class="text-gray-700">{{ $meta['label'] }}</span>
    @endif
</span>
