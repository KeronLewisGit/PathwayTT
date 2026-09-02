<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Jobs\RecomputeUserMatchesJob;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'People';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(190),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(190)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
                Forms\Components\DateTimePicker::make('email_verified_at')
                    ->label('Email verified at')
                    ->helperText('Set to skip the verification email for this account.'),
                // is_admin is deliberately not mass-assignable; the pages forceFill it.
                Forms\Components\Toggle::make('is_admin')->label('Administrator')->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_admin')->label('Admin')->boolean(),
                Tables\Columns\IconColumn::make('email_verified_at')->label('Verified')
                    ->boolean()->getStateUsing(fn (User $record) => $record->email_verified_at !== null),
                Tables\Columns\TextColumn::make('job_matches_count')->label('Matches')
                    ->counts('jobMatches')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')->label('Administrators'),
            ])
            ->actions([
                Tables\Actions\Action::make('recompute')
                    ->label('Re-run matching')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (User $record): void {
                        RecomputeUserMatchesJob::dispatch($record->id);

                        Notification::make()
                            ->title("Match recomputation queued for {$record->name}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
