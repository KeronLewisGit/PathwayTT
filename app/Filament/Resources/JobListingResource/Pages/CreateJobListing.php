<?php

namespace App\Filament\Resources\JobListingResource\Pages;

use App\Filament\Resources\JobListingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateJobListing extends CreateRecord
{
    protected static string $resource = JobListingResource::class;

    /** Admin-entered listings are the "manual" source (see ManualSource). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = 'manual';
        $data['source_job_id'] ??= (string) Str::uuid();

        return $data;
    }
}
