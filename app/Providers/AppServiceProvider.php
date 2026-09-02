<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\Advisory\SkillGapAnalyzer;
use App\Services\Advisory\SkillGapAnalyzerInterface;
use App\Services\Matching\MatchScorerInterface;
use App\Services\Matching\MatchScoringService;
use App\Services\Resume\LlmStructurer;
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
            $apiKey = (string) config('resume.anthropic.api_key');

            if ($driver === 'llm' && $apiKey === '') {
                Log::warning('RESUME_PARSER_DRIVER=llm but ANTHROPIC_API_KEY is empty; using the rule-based structurer.');
            }

            if ($driver === 'llm' && $apiKey !== '') {
                return $this->app->make(LlmStructurer::class);
            }

            return $this->app->make(RuleBasedStructurer::class);
        });

        // Anthropic client for the LLM structurer (only ever resolved when the llm driver is active).
        $this->app->bind(Client::class, fn () => new Client(
            apiKey: (string) config('resume.anthropic.api_key'),
            requestOptions: ['timeout' => (int) config('resume.anthropic.timeout', 120)],
        ));

        // Swappable scorer: tests or a future ML-backed scorer can rebind this.
        $this->app->bind(MatchScorerInterface::class, MatchScoringService::class);
        $this->app->bind(SkillGapAnalyzerInterface::class, SkillGapAnalyzer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
