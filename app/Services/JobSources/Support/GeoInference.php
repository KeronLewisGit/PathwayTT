<?php

namespace App\Services\JobSources\Support;

/**
 * Turns a board's free-text "candidate location" into the three fields the
 * matcher's hard filter uses: geo_eligibility, country, is_open_to_caribbean.
 *
 * Deliberately conservative: unknown text → null (eligibility "not stated"),
 * never a guess in the applicant's favour.
 */
final class GeoInference
{
    /** Phrases meaning "anyone, anywhere". */
    private const WORLDWIDE = ['worldwide', 'anywhere', 'global', 'everywhere', 'any location', 'international', 'all countries', 'remote only'];

    /** Regions that include Trinidad & Tobago. */
    private const OPEN_REGIONS = ['latam', 'latin america', 'caribbean', 'americas', 'south america', 'central america', 'western hemisphere', 'amer'];

    /** Regions that exclude it. */
    private const CLOSED_REGIONS = ['europe', 'european', 'emea', 'eu', 'apac', 'asia', 'africa', 'north america', 'oceania', 'middle east', 'nordics', 'dach', 'anz', 'benelux', 'uk & ireland', 'united states timezones', 'us timezones', 'usa timezones', 'us time zones'];

    /** Country phrases → ISO-3166 alpha-2. */
    private const COUNTRIES = [
        'trinidad and tobago' => 'TT', 'trinidad & tobago' => 'TT', 'trinidad' => 'TT',
        'united states' => 'US', 'united states of america' => 'US', 'usa' => 'US', 'u.s.' => 'US', 'u.s.a.' => 'US', 'us only' => 'US', 'us-only' => 'US', 'us-based' => 'US',
        'canada' => 'CA', 'united kingdom' => 'GB', 'uk' => 'GB', 'england' => 'GB', 'ireland' => 'IE',
        'germany' => 'DE', 'france' => 'FR', 'spain' => 'ES', 'portugal' => 'PT', 'italy' => 'IT', 'netherlands' => 'NL',
        'poland' => 'PL', 'sweden' => 'SE', 'norway' => 'NO', 'denmark' => 'DK', 'finland' => 'FI', 'switzerland' => 'CH', 'austria' => 'AT', 'belgium' => 'BE',
        'australia' => 'AU', 'new zealand' => 'NZ', 'india' => 'IN', 'philippines' => 'PH', 'singapore' => 'SG', 'japan' => 'JP', 'israel' => 'IL',
        'brazil' => 'BR', 'mexico' => 'MX', 'argentina' => 'AR', 'colombia' => 'CO', 'chile' => 'CL', 'peru' => 'PE',
        'jamaica' => 'JM', 'barbados' => 'BB', 'guyana' => 'GY', 'south africa' => 'ZA', 'nigeria' => 'NG', 'kenya' => 'KE',
    ];

    /**
     * @param string|list<string>|null $location free text ("USA, Canada"), or a list of country names
     * @return array{geo: ?string, country: ?string, open: ?bool}
     */
    public static function infer(string|array|null $location): array
    {
        $text = is_array($location) ? implode(', ', $location) : (string) $location;
        $text = mb_strtolower(trim(html_entity_decode($text)));

        $none = ['geo' => null, 'country' => null, 'open' => null];
        if ($text === '' || $text === 'remote') {
            return $none;
        }

        foreach (self::WORLDWIDE as $phrase) {
            if (self::has($text, $phrase)) {
                return ['geo' => 'worldwide', 'country' => null, 'open' => true];
            }
        }

        $countries = [];
        foreach (self::COUNTRIES as $phrase => $code) {
            if (self::has($text, $phrase)) {
                $countries[$code] = true;
            }
        }
        $countries = array_keys($countries);

        $openRegion = false;
        foreach (self::OPEN_REGIONS as $phrase) {
            if (self::has($text, $phrase)) {
                $openRegion = true;
                break;
            }
        }

        $closedRegion = false;
        foreach (self::CLOSED_REGIONS as $phrase) {
            if (self::has($text, $phrase)) {
                $closedRegion = true;
                break;
            }
        }

        if ($openRegion || in_array('TT', $countries, true)) {
            return [
                'geo' => 'region_restricted',
                'country' => $countries === ['TT'] ? 'TT' : null,
                'open' => true,
            ];
        }

        if (count($countries) === 1 && ! $closedRegion) {
            return ['geo' => 'country_restricted', 'country' => $countries[0], 'open' => false];
        }

        if ($countries !== [] || $closedRegion) {
            return ['geo' => 'region_restricted', 'country' => null, 'open' => false];
        }

        return $none; // a city name, or wording we don't recognise
    }

    /**
     * Himalayas-style UTC offset lists: T&T is UTC-4 year-round.
     *
     * @param list<int|float> $offsets
     */
    public static function timezoneAllowsAst(array $offsets): ?bool
    {
        if ($offsets === []) {
            return null;
        }

        return in_array(-4, array_map('floatval', $offsets), true) || in_array(-4.0, array_map('floatval', $offsets), true);
    }

    private static function has(string $text, string $phrase): bool
    {
        return (bool) preg_match('/(?<![a-z])'.preg_quote($phrase, '/').'(?![a-z])/u', $text);
    }
}
