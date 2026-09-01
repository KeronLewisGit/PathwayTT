<?php

namespace App\Enums;

enum ProviderType: string
{
    case LocalTt = 'local_tt';
    case InternationalOnline = 'international_online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::LocalTt => 'Local (Trinidad & Tobago)',
            self::InternationalOnline => 'International / Online',
            self::Hybrid => 'Hybrid',
        };
    }
}
