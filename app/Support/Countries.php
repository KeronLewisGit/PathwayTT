<?php

namespace App\Support;

/**
 * ISO-3166 alpha-2 → display name for the countries that show up on
 * listings (employer country). Unknown codes fall back to the code itself.
 */
final class Countries
{
    private const NAMES = [
        'TT' => 'Trinidad & Tobago', 'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IE' => 'Ireland',
        'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'PT' => 'Portugal', 'IT' => 'Italy', 'NL' => 'Netherlands',
        'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria', 'PL' => 'Poland', 'SE' => 'Sweden', 'NO' => 'Norway',
        'DK' => 'Denmark', 'FI' => 'Finland', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'IN' => 'India', 'PH' => 'Philippines',
        'SG' => 'Singapore', 'JP' => 'Japan', 'IL' => 'Israel', 'AE' => 'United Arab Emirates', 'ZA' => 'South Africa',
        'NG' => 'Nigeria', 'KE' => 'Kenya', 'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina', 'CO' => 'Colombia',
        'CL' => 'Chile', 'PE' => 'Peru', 'JM' => 'Jamaica', 'BB' => 'Barbados', 'GY' => 'Guyana', 'GD' => 'Grenada',
        'LC' => 'Saint Lucia', 'VC' => 'St Vincent & the Grenadines', 'AG' => 'Antigua & Barbuda', 'BS' => 'Bahamas',
        'DO' => 'Dominican Republic', 'PA' => 'Panama', 'CR' => 'Costa Rica', 'KR' => 'South Korea', 'HK' => 'Hong Kong',
        'EE' => 'Estonia', 'LT' => 'Lithuania', 'LV' => 'Latvia', 'CZ' => 'Czechia', 'RO' => 'Romania', 'UA' => 'Ukraine',
        'TR' => 'Türkiye', 'EG' => 'Egypt', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'ID' => 'Indonesia', 'MY' => 'Malaysia',
        'VN' => 'Vietnam', 'TH' => 'Thailand', 'CN' => 'China', 'TW' => 'Taiwan',
    ];

    public static function name(?string $code): string
    {
        $code = strtoupper((string) $code);

        return self::NAMES[$code] ?? ($code !== '' ? $code : 'Not stated');
    }
}
