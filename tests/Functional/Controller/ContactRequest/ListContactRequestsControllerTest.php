<?php

declare(strict_types=1);

use App\Enums\Deadline;
use App\Enums\Priority;
use App\Enums\RequestType;
use App\Models\ContactRequest;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| ListContactRequestsController — HTTP / Inertia layer
|--------------------------------------------------------------------------
| Transport concerns only: Inertia render + prop shape, query-param filters
| echoed back, pagination shape, empty state, and invalid filter → redirect
| back with session errors. Filtering/ordering correctness is covered in
| tests/Feature/Action/ListContactRequestsTest.php.
*/

it('renders the dashboard page with the paginated prop and current filters', function () {
    ContactRequest::factory()->count(3)->create();

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->has('contactRequests.data', 3)
            ->has('contactRequests.links')
            ->where('contactRequests.total', 3)
            ->where('filters.type', null)
            ->where('filters.deadline', null)
        );
});

it('echoes the validated filters back into the Inertia prop', function () {
    ContactRequest::factory()->create([
        'request_type' => RequestType::Quote,
        'deadline' => Deadline::Immediate,
    ]);

    $response = $this->get('/dashboard?type=quote&deadline=immediate');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->where('filters.type', 'quote')
            ->where('filters.deadline', 'immediate')
        );
});

it('paginates at 25 per page and carries filters across pages', function () {
    ContactRequest::factory()->count(30)->create([
        'request_type' => RequestType::Quote,
    ]);

    $this->get('/dashboard?type=quote')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests.data', 25)
            ->where('contactRequests.current_page', 1)
            ->where('contactRequests.last_page', 2)
            ->where('contactRequests.total', 30)
        );

    $this->get('/dashboard?type=quote&page=2')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests.data', 5)
            ->where('contactRequests.current_page', 2)
            ->where('filters.type', 'quote')
        );
});

it('renders an empty data set when no request matches', function () {
    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests.data', 0)
            ->where('contactRequests.total', 0)
        );
});

it('redirects back with session errors when a filter is outside its enum', function () {
    $response = $this->from('/dashboard')->get('/dashboard?type=not-a-type');

    $response->assertRedirect('/dashboard')
        ->assertSessionHasErrors(['type']);
});

it('orders the rows by priority descending in the prop', function () {
    ContactRequest::factory()->create(['name' => 'Low', 'priority' => Priority::Low, 'qualified_at' => now()]);
    ContactRequest::factory()->create(['name' => 'High', 'priority' => Priority::High, 'qualified_at' => now()]);
    ContactRequest::factory()->create(['name' => 'Medium', 'priority' => Priority::Medium, 'qualified_at' => now()]);

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.data.0.name', 'High')
            ->where('contactRequests.data.1.name', 'Medium')
            ->where('contactRequests.data.2.name', 'Low')
        );
});

it('exposes the qualification labels in the row prop', function () {
    $row = ContactRequest::factory()->qualified()->create();

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.data.0.relevance', 'relevant')
            ->where('contactRequests.data.0.lead_quality', $row->lead_quality->value)
            ->where('contactRequests.data.0.priority', $row->priority->value)
            ->whereType('contactRequests.data.0.project_type', 'string')
            ->whereType('contactRequests.data.0.summary', 'string')
            ->whereType('contactRequests.data.0.estimated_amount_min', 'integer')
            ->whereType('contactRequests.data.0.qualified_at', 'string')
        );
});

it('exposes a not-yet-qualified row with null labels', function () {
    ContactRequest::factory()->unqualified()->create();

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.data.0.qualified_at', null)
            ->where('contactRequests.data.0.priority', null)
            ->where('contactRequests.data.0.relevance', null)
        );
});

it('flags a missing-info-acknowledged row with no estimate in the prop', function () {
    ContactRequest::factory()->missingInfoAcknowledged()->create();

    $this->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.data.0.missing_info_acknowledged', true)
            ->where('contactRequests.data.0.estimated_amount_min', null)
            ->where('contactRequests.data.0.estimated_amount_max', null)
        );
});
