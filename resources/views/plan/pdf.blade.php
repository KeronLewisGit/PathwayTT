<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Skills Gap Plan — {{ $user->name }}</title>
    <style>
        /* dompdf: keep to simple CSS 2.1 — no flex/grid. */
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 20pt; color: #213c5f; margin: 0 0 2mm; }
        h2 { font-size: 13.5pt; color: #213c5f; border-bottom: 1.5px solid #213c5f; padding-bottom: 1.5mm; margin: 9mm 0 3mm; }
        h3 { font-size: 11.5pt; margin: 5mm 0 1.5mm; color: #111827; }
        p { margin: 0 0 2mm; }
        .muted { color: #6b7280; font-size: 9pt; }
        .header { border-bottom: 3px solid #213c5f; padding-bottom: 3mm; margin-bottom: 4mm; }
        .brand { font-size: 9pt; letter-spacing: 1px; text-transform: uppercase; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; }
        th, td { text-align: left; vertical-align: top; padding: 1.6mm 2mm; border-bottom: 1px solid #e5e7eb; font-size: 9.5pt; }
        th { background: #f2f6fb; color: #213c5f; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .tag { display: inline-block; padding: 0.5mm 1.8mm; border-radius: 2mm; font-size: 8.5pt; background: #eef2f7; color: #374151; }
        .tag-done { background: #dcfce7; color: #166534; }
        .tag-req { background: #fee2e2; color: #991b1b; }
        .item { margin: 0 0 5mm; padding: 3mm 3.5mm; border: 1px solid #e5e7eb; border-left: 4px solid #213c5f; }
        .item.done { border-left-color: #16a34a; }
        .track { width: 49%; display: inline-block; vertical-align: top; }
        .track + .track { margin-left: 1.5%; }
        .track h4 { margin: 2mm 0 1mm; font-size: 9.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .res { margin: 0 0 1.5mm; padding-left: 2mm; border-left: 2px solid #d1d5db; font-size: 9.3pt; }
        .res strong { color: #111827; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
        .advice { margin: 0 0 3mm; }
        .kpi { display: inline-block; width: 31%; margin-right: 2%; padding: 3mm; background: #f2f6fb; border-radius: 2mm; vertical-align: top; }
        .kpi .v { font-size: 18pt; font-weight: bold; color: #213c5f; }
        .kpi .l { font-size: 8.5pt; color: #6b7280; }
    </style>
</head>
<body>
    <div class="footer">PathwayTT · Skills Gap Plan for {{ $user->name }} · generated {{ $generatedAt->format('d M Y, H:i') }} AST · Projections are computed from listings open on that date.</div>

    <div class="header">
        <div class="brand">PathwayTT · Employment assistance for Trinidad &amp; Tobago</div>
        <h1>Skills Gap Plan</h1>
        <p>Prepared for <strong>{{ $user->name }}</strong> on {{ $generatedAt->format('d F Y') }}
            @if (! empty($payload['scope']['industry'])) · target industry: <strong>{{ $payload['scope']['industry'] }}</strong>@endif
        </p>
        <p class="muted">
            Based on {{ $payload['scope']['listings_considered'] ?? 0 }} open listings you are eligible for from Trinidad &amp; Tobago
            @if (! empty($payload['scope']['widened'])) (your preferred scope had too few, so all open listings were used) @endif.
            Score lift and “listings unlocked” figures are calculated by re-scoring those real listings with each skill added.
        </p>
    </div>

    @php
        $current = $payload['current'] ?? [];
        $threshold = (int) ($payload['threshold'] ?? 55);
        $gaps = collect($payload['gaps'] ?? []);
        $closed = $gaps->filter(fn ($g) => in_array($g['skill']['id'], $doneSkillIds, true))->count();
    @endphp

    <div>
        <div class="kpi"><div class="v">{{ $current['best_score'] ?? 0 }}<span style="font-size:9pt;font-weight:normal;color:#9ca3af"> / 100</span></div><div class="l">Best match score when this plan was made</div></div>
        <div class="kpi"><div class="v">{{ $current['above_threshold'] ?? 0 }} / {{ $current['eligible_count'] ?? 0 }}</div><div class="l">Eligible listings scoring {{ $threshold }} or more</div></div>
        <div class="kpi"><div class="v">{{ $closed }} / {{ $gaps->count() }}</div><div class="l">Gaps already closed on your profile</div></div>
    </div>

    <h2>Priority order</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Skill</th>
                <th class="num">Listings requiring it</th>
                <th class="num">Would unlock</th>
                <th class="num">Avg. score lift</th>
                <th class="num">Effort</th>
                <th>Phase</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($gaps as $gap)
                <tr>
                    <td>{{ $gap['rank'] }}</td>
                    <td>{{ $gap['skill']['name'] }} @if (in_array($gap['skill']['id'], $doneSkillIds, true))<span class="tag tag-done">done</span>@endif</td>
                    <td class="num">{{ $gap['jobs_requiring'] }}</td>
                    <td class="num">{{ $gap['jobs_unlocked'] }}</td>
                    <td class="num">+{{ $gap['avg_lift'] }}</td>
                    <td class="num">~{{ $gap['effort_weeks'] }} wk{{ $gap['effort_estimated'] ? '*' : '' }}</td>
                    <td>{{ ['quick' => 'Quick win', 'core' => 'Core credential', 'long' => 'Long-term'][$gap['phase']] ?? $gap['phase'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7">You already hold every skill the listings in scope ask for.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="muted">* Effort is an estimate from the credential type; confirm dates and duration with the provider.</p>

    @foreach ($payload['phases'] ?? [] as $phase)
        <h2>{{ $phase['title'] }} <span class="muted" style="font-weight:normal">({{ $phase['window'] }})</span></h2>

        @if ($phase['items'] === [])
            <p class="muted">Nothing in this window.</p>
        @endif

        @foreach ($phase['items'] as $item)
            @php $done = in_array($item['skill']['id'], $doneSkillIds, true); @endphp
            <div class="item {{ $done ? 'done' : '' }}">
                <h3>{{ $item['rank'] }}. {{ $item['skill']['name'] }} @if ($done)<span class="tag tag-done">already on your profile</span>@endif</h3>
                <p>
                    Required by {{ $item['jobs_requiring'] }} {{ Str::plural('listing', $item['jobs_requiring']) }}@if ($item['jobs_preferring']), nice-to-have in {{ $item['jobs_preferring'] }}@endif.
                    Adding it would @if ($item['jobs_unlocked'] > 0)<strong>unlock {{ $item['jobs_unlocked'] }} {{ Str::plural('listing', $item['jobs_unlocked']) }}</strong> and @endif
                    lift your score by about <strong>+{{ $item['avg_lift'] }}</strong> where it applies; your best would become <strong>{{ $item['best_after'] }}</strong>.
                    Estimated effort ~{{ $item['effort_weeks'] }} {{ Str::plural('week', $item['effort_weeks']) }}@if ($item['effort_estimated']) (estimate)@endif.
                </p>

                <div class="track">
                    <h4>Locally in Trinidad &amp; Tobago</h4>
                    @forelse ($item['resources']['local'] as $r)
                        <div class="res">
                            <strong>{{ $r['provider'] }}</strong> — {{ $r['title'] }}<br>
                            <span class="muted">
                                {{ $r['credential_type'] ?: 'credential not stated' }} ·
                                @if ($r['duration_weeks']) {{ $r['duration_weeks'] }} wk @else ~{{ $r['effort_weeks'] }} wk (est.) @endif ·
                                {{ $r['cost'] ?: ($r['cost_note'] ?: 'contact provider for fees') }}
                                @if ($r['url']) · {{ $r['url'] }} @endif
                            </span>
                        </div>
                    @empty
                        <p class="muted">No local provider catalogued yet.</p>
                    @endforelse
                </div><div class="track">
                    <h4>Online / international</h4>
                    @forelse ($item['resources']['online'] as $r)
                        <div class="res">
                            <strong>{{ $r['provider'] }}</strong> — {{ $r['title'] }}<br>
                            <span class="muted">
                                {{ $r['credential_type'] ?: 'credential not stated' }} ·
                                @if ($r['duration_weeks']) {{ $r['duration_weeks'] }} wk @else ~{{ $r['effort_weeks'] }} wk (est.) @endif ·
                                {{ $r['cost'] ?: ($r['cost_note'] ?: 'cost not listed') }}
                                @if ($r['url']) · {{ $r['url'] }} @endif
                            </span>
                        </div>
                    @empty
                        <p class="muted">No online provider catalogued yet.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    @endforeach

    @if (! empty($payload['advice']))
        <h2>Beyond courses</h2>
        @foreach ($payload['advice'] as $tip)
            <div class="advice">
                <strong>{{ $tip['title'] }}</strong><br>
                {{ $tip['body'] }}
            </div>
        @endforeach
    @endif

    <p class="muted" style="margin-top:8mm">
        Course names, fees and durations are shown only where a provider or PathwayTT administrator has supplied them; everything else says “contact provider”.
        Listings and projections reflect the job market on the date above and change as new jobs are posted.
    </p>
</body>
</html>
