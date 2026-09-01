<?php

namespace App\Enums;

enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Temporary = 'temporary';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
