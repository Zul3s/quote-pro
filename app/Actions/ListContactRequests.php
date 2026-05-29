<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ListContactRequestsData;
use App\Models\ContactRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read use case: lists incoming contact requests for the artisan dashboard as a
 * follow-up queue — highest priority first, then newest — with optional
 * server-side filters on request type and deadline.
 *
 * Returns a paginated result (25 per page) carrying the active query string so
 * the front keeps filters across pages. The LengthAwarePaginator return type is
 * the assumed extension of the "read → ?Model/Collection" contract to support
 * server-side pagination.
 */
final readonly class ListContactRequests
{
    public function handle(ListContactRequestsData $data): LengthAwarePaginator
    {
        return ContactRequest::query()
            ->when($data->type, fn ($query, $type) => $query->where('request_type', $type))
            ->when($data->deadline, fn ($query, $deadline) => $query->where('deadline', $deadline))
            ->orderByRaw("CASE priority WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
    }
}
