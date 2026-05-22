<?php

use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\ValidationsException;
use App\Infrastructure\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Convert Domain exceptions into HTTP-friendly responses.
        // Laravel's ValidationException already knows how to respond:
        //   - Inertia/web: redirect back with $errors in session
        //   - API/JSON: 422 with structured payload
        $exceptions->render(function (ValidationsException $e): void {
            $errors = [];
            foreach ($e->reasons as $reason) {
                $key = $reason->propertyPath ?? '_';
                $errors[$key][] = $reason->messageKey;
            }
            throw ValidationException::withMessages($errors);
        });

        $exceptions->render(function (EntityNotFoundException $e): void {
            throw new NotFoundHttpException($e->getMessage(), $e);
        });
    })->create();
