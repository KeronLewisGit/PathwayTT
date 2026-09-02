@props(['match'])

@php
    $breakdown = $match->score_breakdown ?? [];
    $components = $breakdown['components'] ?? [];
@endphp

<div {{ $attributes->merge(['class' => 'text-sm']) }}>
    @if ($components === [])
        <p class="text-gray-500">No breakdown available.</p>
    @else
        <div class="scroll-x">
        <table class="w-full min-w-[28rem] text-left">
            <thead class="text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="py-1 pr-3 font-medium">Factor</th>
                    <th class="py-1 pr-3 font-medium">Why</th>
                    <th class="py-1 text-right font-medium">Points</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($components as $component)
                    <tr class="{{ $component['applicable'] ? '' : 'text-gray-400' }}">
                        <td class="py-1.5 pr-3 whitespace-nowrap align-top font-medium">{{ $component['label'] }}</td>
                        <td class="py-1.5 pr-3 align-top">{{ $component['detail'] }}</td>
                        <td class="py-1.5 text-right whitespace-nowrap align-top">
                            @if ($component['applicable'])
                                {{ $component['points'] }} <span class="text-gray-400">/ {{ $component['weight'] }}</span>
                            @else
                                <span class="text-gray-400">n/a</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if (! empty($breakdown['cap']))
                    <tr class="text-amber-800">
                        <td class="pt-2 pr-3 font-medium" colspan="2">{{ $breakdown['cap_reason'] }}</td>
                        <td class="pt-2 text-right">max {{ $breakdown['cap'] }}</td>
                    </tr>
                @endif
                <tr class="font-semibold text-gray-900">
                    <td class="pt-2 pr-3" colspan="2">Match score</td>
                    <td class="pt-2 text-right">{{ $match->score }} / 100</td>
                </tr>
            </tfoot>
        </table>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Factors a listing doesn't state are marked n/a and their weight is shared across the rest.
        </p>
    @endif
</div>
