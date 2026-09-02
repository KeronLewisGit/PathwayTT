<?php

namespace App\Filament\Pages;

use App\Jobs\RecomputeAllMatchesJob;
use App\Services\Matching\MatchScoringService;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin-editable matching weights, advisory threshold and FX rate.
 * Values persist in the settings table (SettingsService) and override
 * config/matching.php defaults at runtime — nothing is hardcoded in views.
 */
class MatchingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Matching & FX';

    protected static ?string $title = 'Matching weights & FX rate';

    protected static string $view = 'filament.pages.matching-settings';

    public ?array $data = [];

    public function mount(SettingsService $settings): void
    {
        $fx = $settings->get('fx.usd_to_ttd');

        $this->form->fill([
            'weights' => $settings->matchingWeights(),
            'advisory_threshold' => $settings->advisoryThreshold(),
            'fx_rate' => is_array($fx) ? ($fx['rate'] ?? null) : $fx,
            'fx_note' => is_array($fx) ? ($fx['note'] ?? null) : null,
            'fx_needs_review' => is_array($fx) ? (bool) ($fx['needs_review'] ?? false) : false,
            'recompute' => true,
        ]);
    }

    public function form(Form $form): Form
    {
        $weightInputs = [];
        foreach (MatchScoringService::labels() as $key => $label) {
            $weightInputs[] = Forms\Components\TextInput::make("weights.{$key}")
                ->label($label)
                ->numeric()->integer()->minValue(0)->maxValue(100)->required();
        }

        return $form->schema([
            Forms\Components\Section::make('Score weights')
                ->description('Must add up to 100. Factors a listing does not state are skipped and their weight is shared across the rest.')
                ->columns(4)
                ->schema($weightInputs),

            Forms\Components\Section::make('Advisory mode')->columns(2)->schema([
                Forms\Components\TextInput::make('advisory_threshold')
                    ->label('Advisory threshold')
                    ->numeric()->integer()->minValue(0)->maxValue(100)->required()
                    ->helperText('If a user\'s best match scores below this, the app pivots to the Skills Gap Plan.'),
            ]),

            Forms\Components\Section::make('Exchange rate')->columns(3)->schema([
                Forms\Components\TextInput::make('fx_rate')
                    ->label('TTD per 1 USD')
                    ->numeric()->minValue(0.01)->step(0.0001)->required()
                    ->helperText('Used to show every salary in both currencies.'),
                Forms\Components\TextInput::make('fx_note')->label('Note / source')->maxLength(190),
                Forms\Components\Toggle::make('fx_needs_review')->label('Flag as needing review')->inline(false),
            ]),

            Forms\Components\Toggle::make('recompute')
                ->label('Recompute every user\'s matches after saving')
                ->helperText('Queued; runs in the background.'),
        ])->statePath('data');
    }

    public function save(SettingsService $settings): void
    {
        $data = $this->form->getState();

        $weights = array_map('intval', $data['weights']);
        if (array_sum($weights) !== 100) {
            Notification::make()
                ->title('Weights must add up to 100')
                ->body('They currently add up to '.array_sum($weights).'.')
                ->danger()
                ->send();

            return;
        }

        $settings->set('matching.weights', $weights);
        $settings->set('matching.advisory_threshold', (int) $data['advisory_threshold']);
        $settings->set('fx.usd_to_ttd', [
            'rate' => (float) $data['fx_rate'],
            'note' => $data['fx_note'] ?: null,
            'needs_review' => (bool) $data['fx_needs_review'],
        ]);

        if ($data['recompute'] ?? false) {
            RecomputeAllMatchesJob::dispatch();
        }

        Notification::make()
            ->title('Settings saved')
            ->body(($data['recompute'] ?? false) ? 'Match recomputation has been queued for all users.' : null)
            ->success()
            ->send();
    }
}
