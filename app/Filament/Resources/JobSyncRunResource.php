<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobSyncRunResource\Pages;
use App\Models\JobSyncRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Job-sync dashboard: one row per source per job:sync run, with the
 * CSV upload + "run now" actions on the list page. Read-only otherwise.
 */
class JobSyncRunResource extends Resource
{
    protected static ?string $model = JobSyncRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Jobs';

    protected static ?string $navigationLabel = 'Job sync';

    protected static ?string $modelLabel = 'sync run';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(fn (JobSyncRun $record) => match (true) {
                        $record->error !== null => 'failed',
                        $record->finished_at === null => 'running',
                        default => 'ok',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime('d M Y H:i', config('app.display_timezone'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->state(fn (JobSyncRun $record) => $record->finished_at
                        ? (int) $record->started_at->diffInSeconds($record->finished_at).'s'
                        : '-'),
                Tables\Columns\TextColumn::make('fetched_count')->label('Fetched')->numeric(),
                Tables\Columns\TextColumn::make('created_count')->label('Created')->numeric(),
                Tables\Columns\TextColumn::make('updated_count')->label('Updated')->numeric(),
                Tables\Columns\TextColumn::make('notes')->limit(60)->wrap()->placeholder('—')
                    ->tooltip(fn (JobSyncRun $record) => $record->notes),
                Tables\Columns\TextColumn::make('error')->limit(80)->wrap()->placeholder('—')
                    ->tooltip(fn (JobSyncRun $record) => $record->error),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options(fn () => JobSyncRun::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
                Tables\Filters\Filter::make('failed')
                    ->query(fn ($query) => $query->whereNotNull('error'))
                    ->toggle(),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobSyncRuns::route('/'),
        ];
    }
}
