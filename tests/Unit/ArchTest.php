<?php

declare(strict_types=1);

use App\Application\UseCase\AbstractRequest;
use App\Application\UseCase\AbstractResponse;

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
|
| Guarantee the DDD layering: Domain has zero framework dependency,
| Application stays away from Eloquent/HTTP, and naming conventions hold.
| See docs/architecture.md for the full ruleset.
|
*/

arch('Domain only depends on PHP, Domain itself, and approved value-object libs')
    ->expect('App\Domain')
    ->toOnlyUse([
        // Domain peut s'auto-référencer
        'App\Domain',
        // Bibliothèques de Value Objects acceptées (PHP-only, sans framework)
        'Ramsey\Uuid',
        'DateTimeImmutable',
        // Exceptions PHP standard que les Exception métier peuvent étendre
        'RuntimeException',
    ]);

arch('Application only depends on Domain, itself, and explicitly allowed libs')
    ->expect('App\Application')
    ->toOnlyUse([
        // Auto-référence + Domain
        'App\Application',
        'App\Domain',
        // Value Objects PHP-only manipulés par les Use Cases
        'Ramsey\Uuid',
        // laravel-data autorisé exclusivement ici (voir docs/architecture.md §3)
        'Spatie\LaravelData',
    ]);

arch('Domain Entity namespace contains only interfaces or readonly DTOs')
    ->expect('App\Domain\Entity')
    ->toBeInterfaces();

arch('Domain Repository namespace contains only interfaces')
    ->expect('App\Domain\Repository')
    ->toBeInterfaces();

arch('Domain Factory namespace contains only interfaces')
    ->expect('App\Domain\Factory')
    ->toBeInterfaces();

arch('AbstractRequest and AbstractResponse remain abstract')
    ->expect([AbstractRequest::class, AbstractResponse::class])
    ->toBeAbstract();

arch('Domain Events are final and readonly')
    ->expect('App\Domain\Event\User')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('Specifications extend AbstractSpecification')
    ->expect('App\Domain\Specification')
    ->classes()
    ->toExtend('App\Domain\Specification\AbstractSpecification')
    ->ignoring('App\Domain\Specification\AbstractSpecification');

arch('Use Case classes are final')
    ->expect('App\Application\UseCase')
    ->classes()
    ->toBeFinal()
    ->ignoring([AbstractRequest::class, AbstractResponse::class]);

arch('Eloquent models live only in Infrastructure')
    ->expect('Illuminate\Database\Eloquent\Model')
    ->not->toBeUsedIn(['App\Domain', 'App\Application']);
