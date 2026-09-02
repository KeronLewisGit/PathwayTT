<?php

namespace App\Providers;

use App\Services\Matching\MatchScorerInterface;
use App\Services\Matching\MatchScoringService;
use App\Services\Resume\ResumeStructurerInterface;
use App\Services\Resume\RuleBasedStructurer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResumeStructurerInterface::class, function () {
            $driver = config('resume.driver', 'rule');

            if ($driver === 'llm') {
                // LlmStructurer ships in Phase 7. Until then fall back to the
                // rule-based driver rather than failing parses.
                Log::warning('RESUME_PARSER_DRIVER=llm requested but LlmStructurer is not yet available; using rule-based structurer.');
            }

            return $this->app->make(RuleBasedStructurer::class);
        });

        // Swappable scorer: tests or a future ML-backed scorer can rebind this.
        $this->app->bind(MatchScorerInterface::class, MatchScoringService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
