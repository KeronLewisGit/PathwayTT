<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable runtime settings with config fallback.
 *
 * config/* files hold the shipped defaults; a row in the `settings` table
 * (edited via Filament) overrides them at runtime. Values are cached for a
 * short TTL so shared-host page loads don't hit the table repeatedly.
 */
class SettingsService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Runtime setting with config default fallback.
     *
     * @param string $key        Setting key, e.g. "matching.weights" or "fx.usd_to_ttd".
     * @param string|null $configKey Config key holding the default; defaults to $key.
     */
    public function get(string $key, ?string $configKey = null, mixed $default = null): mixed
    {
        $value = Cache::remember(
            "settings.{$key}",
            self::CACHE_TTL_SECONDS,
            fn () => Setting::query()->where('key', $key)->value('value'),
        );

        return $value ?? config($configKey ?? $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.{$key}");
    }

    /** TTD per 1 USD, admin-editable. */
    public function usdToTtdRate(): ?float
    {
        $fx = $this->get('fx.usd_to_ttd', default: null);

        return is_array($fx) ? (float) ($fx['rate'] ?? 0) ?: null : ($fx !== null ? (float) $fx : null);
    }

    /** Matching weights: settings override merged over config defaults. */
    public function matchingWeights(): array
    {
        $override = $this->get('matching.weights', default: null);
        $defaults = config('matching.weights');

        return is_array($override) ? array_merge($defaults, $override) : $defaults;
    }
}
