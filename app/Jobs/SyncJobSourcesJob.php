<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Queued `job:sync` — used for on-demand refreshes when the feed is stale.
 * Unique so a burst of page views queues at most one run; every board
 * adapter still enforces its own rate-limit window inside the command.
 */
class SyncJobSourcesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    /** Long-running: kept off the user-facing queue so resume parses never wait behind a crawl. */
    public function __construct()
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Artisan::call('job:sync');
    }
}
