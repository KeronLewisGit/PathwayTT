<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Shared-hosting queue fallback (Hostinger / cPanel)
|--------------------------------------------------------------------------
| Shared hosts can't run a persistent `queue:work` daemon. Instead, a single
| cron entry drives everything:
|
|   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
| Each minute this starts two background workers, each exiting when its
| queue is empty (or after 50s so runs never pile up):
|
|   default — user-facing jobs: resume parsing, per-user match recompute,
|             gap plans. Must never wait behind a crawl.
|   sync    — board crawls (up to 10 min) and the post-sync match fan-out.
|
| The overlap locks expire quickly so a worker killed by the host cannot
| block the queue for long. On a VPS with a supervisor-managed worker, set
| QUEUE_VIA_SCHEDULER=false to disable it.
*/
if (config('queue.via_scheduler')) {
    Schedule::command('queue:work', [
        '--queue=default',
        '--stop-when-empty',
        '--max-time=50',
        '--tries=3',
        '--backoff=10',
    ])->name('queue-default')->everyMinute()->withoutOverlapping(5)->runInBackground();

    Schedule::command('queue:work', [
        '--queue=sync',
        '--stop-when-empty',
        '--max-time=50',
        '--tries=3',
        '--backoff=10',
    ])->name('queue-sync')->everyMinute()->withoutOverlapping(15)->runInBackground();
}

/*
|--------------------------------------------------------------------------
| Job ingestion
|--------------------------------------------------------------------------
| Runs every enabled JobSourceInterface adapter (config/jobsources.php):
| the CSV inbox and the remote boards. Hourly keeps the public feed live;
| each board adapter enforces its own rate-limit window (Remotive 6h,
| others 1h), so this never exceeds a board's published limits. The Jobs
| page also queues a sync on demand when the feed is stale, and admins can
| trigger one from the Filament "Job sync" page.
*/
Schedule::command('job:sync')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
