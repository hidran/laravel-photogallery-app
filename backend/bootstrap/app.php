<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force every /api/v1/* request to look JSON-typed so the framework
        // returns the universal JSON envelope (DESIGN.md §11.1) and never
        // a Whoops/HTML error page.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // DESIGN.md §11.1 — every non-2xx response on /api/* uses a uniform
        // envelope. Validation already returns { message, errors } via
        // Laravel's default JsonResponse, so we don't override 422.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        // Note on prepareException: Laravel 13 converts AuthorizationException
        // → AccessDeniedHttpException and ModelNotFoundException →
        // NotFoundHttpException BEFORE these render callbacks run, so we
        // typehint the prepared types rather than the original ones.

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'File exceeds 10 MB limit.'], 413);
            }
        });

        $exceptions->render(function (UnsupportedMediaTypeHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Only JPG, PNG, WebP supported.'], 415);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;

                return response()
                    ->json(['message' => 'Too many requests.'], 429)
                    ->header('Retry-After', (string) $retryAfter);
            }
        });
    })->create();
