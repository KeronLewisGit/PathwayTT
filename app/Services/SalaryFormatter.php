<?php

namespace App\Services;

use App\Models\JobListing;
use App\Support\Money;

/**
 * Renders a listing's salary as "TTD 9,000–14,000 / month (≈ USD 1,324–2,059)".
 * The secondary currency uses the admin-editable FX rate (SettingsService),
 * never a hardcoded figure. Returns null when the listing has no salary.
 */
class SalaryFormatter
{
    private const PERIODS = [
        'hourly' => 'hour',
        'monthly' => 'month',
        'yearly' => 'year',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function format(JobListing $job): ?string
    {
        $primary = $this->range($job->salary_min_cents, $job->salary_max_cents, $job->salary_currency);

        if ($primary === null) {
            return null;
        }

        $period = self::PERIODS[$job->salary_period] ?? null;
        $label = $primary.($period ? " / {$period}" : '');

        $secondary = $this->converted($job);

        return $secondary ? "{$label} (≈ {$secondary})" : $label;
    }

    /** The equivalent range in the other currency, or null if no rate / unknown currency. */
    public function converted(JobListing $job): ?string
    {
        $currency = strtoupper((string) $job->salary_currency);
        $target = match ($currency) {
            'USD' => 'TTD',
            'TTD' => 'USD',
            default => null,
        };

        $rate = $this->settings->usdToTtdRate();

        if ($target === null || $rate === null) {
            return null;
        }

        // Converted figures are estimates: round to whole units so the UI
        // never implies cent-level precision from an approximate rate.
        $convert = fn (?int $cents) => $cents !== null
            ? (int) (round(Money::convert($cents, $currency, $target, $rate) / 100) * 100)
            : null;

        return $this->range($convert($job->salary_min_cents), $convert($job->salary_max_cents), $target);
    }

    private function range(?int $min, ?int $max, ?string $currency): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }

        $currency = strtoupper((string) $currency) ?: '';

        $text = match (true) {
            $min !== null && $max !== null && $min !== $max => Money::format($min).'–'.Money::format($max),
            $min !== null => Money::format($min).($max === null ? '+' : ''),
            default => 'up to '.Money::format($max),
        };

        return trim("{$currency} {$text}");
    }
}
