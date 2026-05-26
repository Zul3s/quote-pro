<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum Deadline: string
{
    case Immediate = 'immediate';
    case WithinOneMonth = 'within_one_month';
    case OverOneMonth = 'over_one_month';
    case NotUrgent = 'not_urgent';
}
