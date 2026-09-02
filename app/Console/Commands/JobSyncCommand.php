<?php

namespace App\Console\Commands;

use App\Models\JobSyncRun;
use App\Services\JobSources\JobIngestor;
use App\Services\JobSources\JobSourceInterface;
use Illuminate\Console\Command;
use Throwable;

class JobSyncCommand extends Command
{
    protected $signature = 'job:sync {--source= : Run a single source by key}';

    protected $description = 'Fetch, normalize and upsert jobs from all enabled sources';

    public function handle(JobIngestor $ingestor): int
    {
        $exitCode = self::SUCCESS;

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

            $fetched = $created = $updated = 0;

            try {
                foreach ($source->fetch() as $dto) {
                    $fetched++;
                    $result = $ingestor->ingest($dto);
                    $result['created'] ? $created++ : $updated++;
                }

                $run->update([
                    'finished_at' => now(),
                    'fetched_count' => $fetched,
                    'created_count' => $created,
                    'updated_count' => $updated,
                ]);

                $this->info("[{$source->key()}] fetched {$fetched}, created {$created}, updated {$updated}");
            } catch (Throwable $e) {
                $run->update([
                    'finished_at' => now(),
                    'fetched_count' => $fetched,
                    'created_count' => $created,
                    'updated_count' => $updated,
                    'error' => $e->getMessage(),
                ]);

                $this->error("[{$source->key()}] failed: {$e->getMessage()}");
                $exitCode = self::FAILURE;
            }
        }

        // Phase 4 wires queued match recomputation here (new jobs ingested).

        return $exitCode;
    }
}
