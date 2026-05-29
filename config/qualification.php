<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Priority scoring
    |--------------------------------------------------------------------------
    |
    | Estimate tiers (in euros) used by QualifyContactRequest to weigh the € estimate
    | when computing the deterministic priority. An estimate >= estimation_high counts
    | as "high", >= estimation_medium as "medium", otherwise "low".
    |
    */

    'priority' => [
        'estimation_high' => (int) env('QUALIFICATION_ESTIMATION_HIGH', 3000),
        'estimation_medium' => (int) env('QUALIFICATION_ESTIMATION_MEDIUM', 800),
    ],

];
