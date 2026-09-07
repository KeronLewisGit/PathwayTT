<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Models\Industry;
use App\Services\JobSources\HtmlBoardSource;
use Illuminate\Console\Command;

/**
 * Back-fills industry and employment type on local-board listings that were
 * crawled before title classification existed (they had NULLs and vanished
 * under any industry filter). Local boards are crawled once a day, so waiting
 * for the next sync would leave the Jobs page broken for up to 24 hours.
 * Idempotent; never overwrites a value that is already set.
 */
class ReclassifyLocalListingsCommand extends Command
{
    protected $signature = 'jobs:reclassify-local {--all : Re-evaluate listings that already have an industry}';

    protected $description = 'Infer industry / employment type from the title for local-board listings missing them';

    public function handle(): int
    {
        $industryIds = Industry::query()->pluck('id', 'slug')->all();
        $updated = 0;

        JobListing::query()
            ->whereIn('source', ['caribbeanjobs', 'trinidadjob', 'jobstt', 'employtt'])
            ->when(! $this->option('all'), fn ($q) => $q->where(fn ($q) => $q->whereNull('industry_id')->orWhereNull('employment_type')))
            ->chunkById(200, function ($listings) use ($industryIds, &$updated) {
                foreach ($listings as $listing) {
                    $changes = [];

                    if ($listing->industry_id === null || $this->option('all')) {
                        $slug = HtmlBoardSource::industryFromTitle($listing->title, $listing->description);
                        if ($slug !== null && isset($industryIds[$slug]) && $industryIds[$slug] !== $listing->industry_id) {
                            $changes['industry_id'] = $industryIds[$slug];
                        }
                    }

                    if ($listing->employment_type === null) {
                        $type = HtmlBoardSource::employmentFromTitle($listing->title);
                        if ($type !== null) {
                            $changes['employment_type'] = $type;
                        }
                    }

                    if ($changes !== []) {
                        $listing->forceFill($changes)->save();
                        $updated++;
                    }
                }
            });

        $this->info("Reclassified {$updated} listing(s).");

        return self::SUCCESS;
    }
}
