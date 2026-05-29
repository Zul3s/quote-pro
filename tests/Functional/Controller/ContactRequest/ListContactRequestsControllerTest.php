<?php

declare(strict_types=1);

use App\Enums\Deadline;
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
