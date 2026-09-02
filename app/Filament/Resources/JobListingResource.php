<?php

namespace App\Filament\Resources;

use App\Enums\EmploymentType;
use App\Enums\GeoEligibility;
use App\Enums\QualificationType;
use App\Enums\WorkArrangement;
use App\Filament\Resources\JobListingResource\Pages;
use App\Models\JobListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JobListingResource extends Resource
{
    protected static ?string $model = JobListing::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Jobs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Listing')->columns(2)->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(190),
                Forms\Components\TextInput::make('company_name')->label('Company')->maxLength(190),
                Forms\Components\Select::make('industry_id')
                    ->relationship('industry', 'name')->searchable()->preload(),
                Forms\Components\Select::make('work_arrangement')
                    ->options(collect(WorkArrangement::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->required(),
                Forms\Components\Select::make('employment_type')
                    ->options(collect(EmploymentType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Forms\Components\Select::make('seniority')->options([
                    'entry' => 'Entry', 'mid' => 'Mid', 'senior' => 'Senior', 'manager' => 'Manager',
                ]),
                Forms\Components\TextInput::make('location_text')->label('Location'),
                Forms\Components\TextInput::make('country')->length(2)
                    ->helperText('ISO code, e.g. TT, US')->default('TT'),
            ]),

            Forms\Components\Section::make('Eligibility (remote/international)')->columns(3)->schema([
                Forms\Components\Select::make('geo_eligibility')
                    ->options(collect(GeoEligibility::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Forms\Components\Toggle::make('is_open_to_caribbean')->inline(false)
                    ->label('Open to Caribbean applicants'),
                Forms\Components\TextInput::make('required_overlap_hours')->numeric()->minValue(0)->maxValue(12)
                    ->helperText('Hours of overlap with employer timezone'),
            ]),

            Forms\Components\Section::make('Requirements (used by matching)')->columns(4)->schema([
                Forms\Components\TextInput::make('min_years_experience')->label('Min years experience')
                    ->numeric()->minValue(0)->maxValue(60)
                    ->helperText('Blank = estimated from seniority'),
                Forms\Components\Select::make('min_education_level')->label('Min qualification')
                    ->options(collect(QualificationType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Forms\Components\Toggle::make('requires_work_permit')->inline(false)
                    ->label('Requires right to work in employer country')
                    ->helperText('Hard filter for non-T&T employers'),
                Forms\Components\CheckboxList::make('required_credentials')->label('Required local credentials')
                    ->options(JobListing::CREDENTIALS),
            ]),

            Forms\Components\Section::make('Salary')->columns(4)->schema([
                // Admin enters dollars; stored as integer cents.
                Forms\Components\TextInput::make('salary_min_cents')->label('Min')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round($state * 100) : null),
                Forms\Components\TextInput::make('salary_max_cents')->label('Max')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (int) round($state * 100) : null),
                Forms\Components\Select::make('salary_currency')->options(['TTD' => 'TTD', 'USD' => 'USD']),
                Forms\Components\Select::make('salary_period')->options([
                    'hourly' => 'Hourly', 'monthly' => 'Monthly', 'yearly' => 'Yearly',
                ]),
            ]),

            Forms\Components\Section::make('Details')->schema([
                Forms\Components\Textarea::make('description')->rows(6),
                Forms\Components\TagsInput::make('requirements')
                    ->helperText('Free-text requirement lines'),
                Forms\Components\Select::make('skills')
                    ->relationship('skills', 'name')->multiple()->searchable()->preload()
                    ->helperText('Attached skills default to "required"; use the CSV import for fine-grained required/preferred.'),
                Forms\Components\TextInput::make('apply_url')->url()->label('Apply URL')
                    ->helperText('Users always apply on the original posting.'),
                Forms\Components\DateTimePicker::make('posted_at')->default(now()),
                Forms\Components\DateTimePicker::make('closes_at'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('company_name')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('work_arrangement')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('industry.name')->limit(25)->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('posted_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options(fn () => JobListing::query()->distinct()->orderBy('source')->pluck('source', 'source')->all()),
                Tables\Filters\SelectFilter::make('work_arrangement')
                    ->options(collect(WorkArrangement::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            ->defaultSort('posted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobListings::route('/'),
            'create' => Pages\CreateJobListing::route('/create'),
            'edit' => Pages\EditJobListing::route('/{record}/edit'),
        ];
    }
}
