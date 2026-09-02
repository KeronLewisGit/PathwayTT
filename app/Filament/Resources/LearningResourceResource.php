<?php

namespace App\Filament\Resources;

use App\Enums\ProviderType;
use App\Filament\Resources\LearningResourceResource\Pages;
use App\Models\LearningResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Recommendation catalog. Costs and durations are nullable on purpose —
 * the UI says "Contact provider" until an admin fills them in here.
 */
class LearningResourceResource extends Resource
{
    protected static ?string $model = LearningResource::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Advisory';

    protected static ?string $navigationLabel = 'Learning resources';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Provider')->columns(2)->schema([
                Forms\Components\TextInput::make('provider')->required()->maxLength(190)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, ?string $old, $get) => $get('slug') ? null : $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')->required()->maxLength(190)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('title')->label('Offering / course')->required()->maxLength(190)->columnSpanFull(),
                Forms\Components\Select::make('provider_type')->required()
                    ->options(collect(ProviderType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Forms\Components\Select::make('delivery_mode')->options([
                    'in_person' => 'In person', 'online' => 'Online', 'blended' => 'Blended',
                ]),
                Forms\Components\TextInput::make('url')->url()->maxLength(500)->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Cost & duration')
                ->description('Leave blank rather than guess. The plan shows "Contact provider" when these are empty.')
                ->columns(4)
                ->schema([
                    Forms\Components\TextInput::make('cost_min_cents')->label('Cost min')->numeric()
                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round($state * 100) : null),
                    Forms\Components\TextInput::make('cost_max_cents')->label('Cost max')->numeric()
                        ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                        ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round($state * 100) : null),
                    Forms\Components\Select::make('currency')->options(['TTD' => 'TTD', 'USD' => 'USD']),
                    Forms\Components\TextInput::make('duration_weeks')->numeric()->minValue(1)->maxValue(520)->label('Duration (weeks)'),
                    Forms\Components\TextInput::make('cost_note')->maxLength(190)->columnSpan(2)
                        ->helperText('e.g. "Free audit track; certificate is paid"'),
                    Forms\Components\Select::make('credential_type')->options([
                        'badge' => 'Badge', 'certificate' => 'Certificate', 'professional' => 'Professional qualification',
                        'diploma' => 'Diploma', 'degree' => 'Degree',
                    ])->columnSpan(2),
                ]),

            Forms\Components\Section::make('Skills taught')->schema([
                Forms\Components\Select::make('skills')
                    ->relationship('skills', 'name')->multiple()->searchable()->preload()
                    ->helperText('Drives the Skills Gap Plan: a gap is only recommended a resource that teaches that skill.'),
                Forms\Components\Textarea::make('notes')->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->limit(50)->wrap(),
                Tables\Columns\TextColumn::make('provider_type')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => match ($state) {
                        ProviderType::LocalTt => 'success',
                        ProviderType::InternationalOnline => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('credential_type')->toggleable(),
                Tables\Columns\TextColumn::make('duration_weeks')->label('Weeks')->numeric()->placeholder('—'),
                Tables\Columns\TextColumn::make('skills_count')->counts('skills')->label('Skills')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider_type')
                    ->options(collect(ProviderType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Tables\Filters\Filter::make('missing_details')
                    ->label('Missing cost or duration')
                    ->query(fn ($query) => $query->whereNull('duration_weeks')->orWhereNull('cost_min_cents'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('provider');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLearningResources::route('/'),
            'create' => Pages\CreateLearningResource::route('/create'),
            'edit' => Pages\EditLearningResource::route('/{record}/edit'),
        ];
    }
}
