<?php

namespace App\Filament\Resources\JobSyncRunResource\Pages;

use App\Filament\Resources\JobSyncRunResource;
use App\Models\JobSyncRun;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class ListJobSyncRuns extends ListRecords
{
    protected static string $resource = JobSyncRunResource::class;

    /** Manual sync triggers are rate limited (shared-host protection + spec). */
    private const SYNC_RATE_LIMIT_PER_MINUTE = 3;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadCsv')
                ->label('Upload CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Import jobs from CSV')
                ->modalDescription('Use docs/job-import-template.csv. Required columns: title, work_arrangement. Skills are matched to the taxonomy by slug, name or alias; unknown skills are skipped.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('CSV file')
                        ->disk(config('jobsources.csv.disk'))
                        ->directory(config('jobsources.csv.inbox'))
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->preserveFilenames()
                        ->maxSize(5120)
                        ->required(),
                    Forms\Components\Toggle::make('run_now')
                        ->label('Run the CSV import now')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $path = $data['file'];

                    if (! Storage::disk(config('jobsources.csv.disk'))->exists($path)) {
                        Notification::make()->title('Upload failed')->danger()->send();

                        return;
                    }

                    if (! ($data['run_now'] ?? false)) {
                        Notification::make()
                            ->title('CSV queued in the inbox')
                            ->body('It will be ingested on the next scheduled job:sync.')
                            ->success()
                            ->send();

                        return;
                    }

                    $this->runSync('csv');
                }),

            Actions\Action::make('runSync')
                ->label('Run sync now')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Runs every enabled source (manual, CSV inbox, and any remote adapters). Safe to repeat: listings upsert on source + source id.')
                ->action(fn () => $this->runSync()),
        ];
    }

    private function runSync(?string $source = null): void
    {
        $allowed = RateLimiter::attempt(
            key: 'job-sync:'.auth()->id(),
            maxAttempts: self::SYNC_RATE_LIMIT_PER_MINUTE,
            callback: fn () => true,
            decaySeconds: 60,
        );

        if (! $allowed) {
            Notification::make()
                ->title('Too many sync runs')
                ->body('Please wait a minute before running sync again.')
                ->warning()
                ->send();

            return;
        }

        $exitCode = Artisan::call('job:sync', $source ? ['--source' => $source] : []);

        $latest = JobSyncRun::query()->latest('id')->first();
        $summary = $latest
            ? "{$latest->source}: fetched {$latest->fetched_count}, created {$latest->created_count}, updated {$latest->updated_count}"
            : 'No sources ran.';

        $notification = Notification::make()
            ->title($exitCode === 0 ? 'Sync complete' : 'Sync finished with errors')
            ->body($summary);

        $exitCode === 0 ? $notification->success() : $notification->danger();
        $notification->send();
    }
}
