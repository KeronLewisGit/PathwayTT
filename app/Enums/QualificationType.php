<?php

namespace App\Enums;

/**
 * Ordered T&T qualification ladder. rank() powers "education level met"
 * scoring: CSEC/CAPE are first-class baseline qualifications locally.
 */
enum QualificationType: string
{
    case Csec = 'csec';
    case Cape = 'cape';
    case Certificate = 'certificate';
    case Diploma = 'diploma';
    case Associate = 'associate';
    case Bachelors = 'bsc';
    case Masters = 'msc';
    case Doctorate = 'phd';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Csec => 'CSEC / CXC',
            self::Cape => 'CAPE',
            self::Certificate => 'Certificate',
            self::Diploma => 'Diploma',
            self::Associate => 'Associate degree',
            self::Bachelors => "Bachelor's degree",
            self::Masters => "Master's degree",
            self::Doctorate => 'Doctorate',
            self::Professional => 'Professional qualification',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Csec => 1,
            self::Cape => 2,
            self::Certificate => 2,
            self::Diploma => 3,
            self::Associate => 3,
            self::Professional => 4, // ACCA/CIMA etc. treated as degree-comparable
            self::Bachelors => 4,
            self::Masters => 5,
            self::Doctorate => 6,
        };
    }
}
