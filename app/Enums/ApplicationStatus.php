<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Saved = 'saved';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Allowed forward transitions from each status. */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Saved => [self::Applied],
            self::Applied => [self::Interviewing, self::Rejected],
            self::Interviewing => [self::Offer, self::Rejected],
            self::Offer, self::Rejected => [],
        };
    }
}
