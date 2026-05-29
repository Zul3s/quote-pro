<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadQuality: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
}
