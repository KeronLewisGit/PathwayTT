@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'callout-success']) }} role="status">
        {{ $status }}
    </div>
@endif
