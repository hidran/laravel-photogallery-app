<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emit a weak ETag based on the bound model's updated_at and short-circuit
 * to 304 when the client sends a matching If-None-Match header.
 *
 * DESIGN.md §6.0 specifies the ETag value as `W/"<sha1(updated_at)>"`.
 * We hash updated_at rather than the response body so an admin viewing
 * the same photo as the owner (with `urls.original` present) and an
 * anonymous viewer (without it) still share an ETag — the underlying
 * resource hasn't changed.
 *
 * The middleware only fires on successful (200) GET requests where the
 * route binds exactly one Eloquent model with a non-null updated_at —
 * any other shape (list endpoint, mutation, no model binding) is left
 * untouched.
 */
final class ETag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        $model = $this->resolveBoundModel($request);
        if ($model === null) {
            return $response;
        }

        $etag = sprintf('W/"%s"', sha1((string) $model->updated_at));

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        $response->headers->set('ETag', $etag);

        return $response;
    }

    private function resolveBoundModel(Request $request): ?Model
    {
        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $parameter) {
            if ($parameter instanceof Model && $parameter->updated_at !== null) {
                return $parameter;
            }
        }

        return null;
    }
}
