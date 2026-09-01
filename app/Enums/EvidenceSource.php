<?php

namespace App\Enums;

enum EvidenceSource: string
{
    case Resume = 'resume';
    case SelfReported = 'self_reported';
    case Certificate = 'certificate';
}
