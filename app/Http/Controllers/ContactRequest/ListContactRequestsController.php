<?php

declare(strict_types=1);

namespace App\Http\Controllers\ContactRequest;

use App\Actions\ListContactRequests;
use App\Data\ListContactRequestsData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ListContactRequestsController
{
    public function __invoke(Request $request, ListContactRequests $action): Response
    {
        $filters = ListContactRequestsData::fromRequest($request);

        $contactRequests = $action->handle($filters);

        return Inertia::render('dashboard/index', [
            'contactRequests' => $contactRequests,
            'filters' => $filters,
        ]);
    }
}
