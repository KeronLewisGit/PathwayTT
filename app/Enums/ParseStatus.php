<?php

namespace App\Enums;

enum ParseStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Parsed = 'parsed';
    case Failed = 'failed';
}
