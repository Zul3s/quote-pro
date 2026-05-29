<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
|
| The layered-Laravel guardrail (replaces the former hexagonal isolation):
| "HTTP stops at the controller; Actions never see the HTTP layer; Eloquent
| is the persistence engine; business rules and input DTOs have a fixed home."
| See docs/architecture.md.
|
| The "no `new XxxData`" rule lives in ArchDataConstructionTest.php (AST-level,
| which the Pest arch DSL cannot express).
|
*/

arch('Actions are final')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toBeFinal();

arch('Actions never touch the HTTP layer (the seam)')
    ->expect('App\Actions')
    ->not->toUse(['Illuminate\Http', 'Inertia']);

arch('Controllers never hit the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('Controllers never touch Eloquent models (data access goes through Actions)')
    ->expect('App\Http\Controllers')
    ->not->toUse('App\Models');

arch('Input DTOs extend Spatie Data')
    ->expect('App\Data')
    ->toExtend('Spatie\LaravelData\Data');

arch('Business rules implement ValidationRule')
    ->expect('App\Rules')
    ->toImplement('Illuminate\Contracts\Validation\ValidationRule');

arch('Enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('Events are final')
    ->expect('App\Events')
    ->toBeFinal();
