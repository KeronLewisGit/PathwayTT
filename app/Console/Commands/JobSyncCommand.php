<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeAllMatchesJob;
use App\Models\JobSyncRun;
use App\Services\JobSources\JobIngestor;
use App\Services\JobSources\JobSourceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class JobSyncCommand extends Command
{
    protected $signature = 'job:sync {--source= : Run a single source by key}';

    protected $description = 'Fetch, normalize and upsert jobs from all enabled sources';

    public function handle(JobIngestor $ingestor): int
    {
        $exitCode = self::SUCCESS;
        $changed = 0;

        foreach (config('jobsources.sources') as $class) {
            /** @var JobSourceInterface $source */
            $source = app($class);

            if ($this->option('source') && $source->key() !== $this->option('source')) {
                continue;
            }

            if (! $source->enabled()) {
                $this->line("[{$source->key()}] disabled — skipped");

                continue;
            }

            $run = JobSyncRun::query()->create([
                'source' => $source->key(),
                'started_at' => now(),
            ]);

            $fetched = $created = $updated = $skipped = 0;
            $skipReasons = [];

            try {
                foreach ($source->fetch() as $dto) {
                    $fetched++;

                    // One malformed listing must not sink the other few hundred —
                    // and, since a failed run is retried every hour, it would sink
                    // them at the same listing every time.
                    try {
                        $result = $ingestor->ingest($dto);
                    } catch (Throwable $e) {
                        $skipped++;
                        if (count($skipReasons) < 3) {
                            $skipReasons[] = ($dto->sourceJobId ?? '?').': '.mb_substr($e->getMessage(), 0, 120);
                        }
                        report($e);

                        continue;
                    }

                    if ($result['created']) {
                        $created++;
                    } elseif ($result['changed']) {
                        $updated++;
                    }
                }

                $notes = implode(' · ', array_filter([
                    $source->notes(),
                    $skipped > 0 ? "{$skipped} listing(s) skipped — ".implode('; ', $skipReasons) : null,
                ]));

                $run->update([
                    'finished_at' => now(),
                    'fetched_count' => $fetched,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                $this->info("[{$source->key()}] fetched {$fetched}, created {$created}, updated {$updated}, skipped {$skipped}"
                    .($notes !== '' ? " — {$notes}" : ''));
            } catch (Throwable $e) {
                $run->update([
                    'finished_at' => now(),
                    'fetched_count' => $fetched,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'error' => $e->getMessage(),
                    'notes' => $source->notes(),
                ]);

                $this->error("[{$source->key()}] failed: {$e->getMessage()}");
                $exitCode = self::FAILURE;
            }

            $changed += $created + $updated;
        }

        // Local-board rows that predate title classification (or carry the old
        // zero-width-space titles) are back-filled on every run, so the Jobs
        // page never depends on someone remembering a one-off command.
        Artisan::call('jobs:reclassify-local');
        if (preg_match('/Reclassified (\d+)/', Artisan::output(), $m) && (int) $m[1] > 0) {
            $this->line("Back-filled industry/employment type on {$m[1]} local listing(s).");
            $changed += (int) $m[1];
        }

        // New or changed listings invalidate every user's ranking: fan out a
        // queued recompute (one job per user with a profile).
        if ($changed > 0) {
            RecomputeAllMatchesJob::dispatch();
            $this->line("Queued match recomputation for all users ({$changed} listings changed).");
        }

        return $exitCode;
    }
}
