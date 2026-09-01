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
| Each minute this drains the queue, exiting when empty (or after 50s so
| runs never overlap the next minute). On a VPS with a supervisor-managed
| worker, set QUEUE_VIA_SCHEDULER=false to disable it.
*/
if (config('queue.via_scheduler')) {
    Schedule::command('queue:work', [
        '--stop-when-empty',
        '--max-time=50',
        '--tries=3',
        '--backoff=10',
    ])->everyMinute()->withoutOverlapping();
}
