<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force every /api/* request to look like a JSON request to the framework.
 *
 * Without this, a client that omits `Accept: application/json` would have
 * Laravel's exception handler render HTML/blade error pages, leaking the
 * Whoops debug screen in dev and bypassing the universal JSON envelope
 * defined in DESIGN.md §11.1.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
