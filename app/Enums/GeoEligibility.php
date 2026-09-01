<?php

namespace App\Enums;

enum GeoEligibility: string
{
    case Worldwide = 'worldwide';
    case RegionRestricted = 'region_restricted';
    case CountryRestricted = 'country_restricted';

    public function label(): string
    {
        return match ($this) {
            self::Worldwide => 'Worldwide',
            self::RegionRestricted => 'Region-restricted',
            self::CountryRestricted => 'Country-restricted',
        };
    }
}
