<?php

declare(strict_types=1);

namespace App\Enums;

enum Relevance: string
{
    case Relevant = 'relevant';
    case OutOfArea = 'out_of_area';
    case OutOfTrade = 'out_of_trade';
    case Spam = 'spam';
}
