<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum RequestType: string
{
    case Quote = 'quote';
    case Information = 'information';
    case Urgent = 'urgent';
    case Other = 'other';
}
