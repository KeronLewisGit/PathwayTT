<?php

namespace App\Services\JobSources;

use App\Models\JobListing;
use App\Models\JobSyncRun;
use Illuminate\Support\Carbon;

/**
 * What the Jobs page says about the live feed: how fresh it is, which
 * sources are contributing, and whether a refresh should be queued.
 */
class FeedStatus
{
    /**
     * @return array{updated_at: ?Carbon, stale: bool, sources: list<array{key:string,label:string,count:int}>, total:int}
     */
    public function summary(): array
    {
        $lastFetch = JobSyncRun::query()
            ->whereNotIn('source', ['manual', 'csv'])
            ->whereNull('error')
            ->whereNotNull('finished_at')
            ->where('fetched_count', '>', 0) // a throttled no-op run is not a fetch
            ->max('finished_at');

        $updatedAt = $lastFetch ? Carbon::parse($lastFetch) : null;
        $minutes = (int) config('jobsources.auto_refresh_minutes', 60);

        $counts = JobListing::query()->active()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $labels = config('jobsources.labels', []);
        $sources = $counts->map(fn ($count, $key) => [
            'key' => $key,
            'label' => $labels[$key]['label'] ?? ucfirst($key),
            'count' => (int) $count,
        ])->sortByDesc('count')->values()->all();

        return [
            'updated_at' => $updatedAt,
            'stale' => $minutes > 0 && ($updatedAt === null || $updatedAt->lt(now()->subMinutes($minutes))),
            'sources' => $sources,
            'total' => (int) $counts->sum(),
        ];
    }
}
