<?php

namespace App\Enums;

enum WorkArrangement: string
{
    // Internationally remote: hosted by a foreign company, performable from T&T.
    case RemoteInternational = 'remote_international';
    case OnPremises = 'on_premises';
    // Local employer, mix of home and office within T&T.
    case HybridLocal = 'hybrid_local';

    public function label(): string
    {
        return match ($this) {
            self::RemoteInternational => 'Remote (international)',
            self::OnPremises => 'On-premises (local)',
            self::HybridLocal => 'Hybrid (local)',
        };
    }
}
