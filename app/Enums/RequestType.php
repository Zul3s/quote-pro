<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestType: string
{
    case Quote = 'quote';
    case Information = 'information';
    case Urgent = 'urgent';
    case Other = 'other';
}
