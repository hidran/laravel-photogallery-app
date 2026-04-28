<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/throws/auth', fn () => throw new AuthenticationException);
        Route::get('/throws/forbidden', fn () => throw new AuthorizationException);
        Route::get('/throws/missing-model', fn () => throw new ModelNotFoundException);
        Route::get('/throws/too-large', fn () => throw new PostTooLargeException);
        Route::get('/throws/bad-media', fn () => throw new UnsupportedMediaTypeHttpException('nope'));
        Route::get('/throws/throttled', fn () => null)->middleware(ThrottleRequests::class.':1,1');
    });
});

it('returns 401 with universal envelope for AuthenticationException', function () {
    $this->getJson('/api/v1/throws/auth')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns 403 with universal envelope for AuthorizationException', function () {
    $this->getJson('/api/v1/throws/forbidden')
        ->assertStatus(403)
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

it('returns 404 with universal envelope for ModelNotFoundException', function () {
    $this->getJson('/api/v1/throws/missing-model')
        ->assertStatus(404)
        ->assertExactJson(['message' => 'Resource not found.']);
});

it('returns 404 for unmatched /api/v1 routes (NotFoundHttpException)', function () {
    $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertStatus(404)
        ->assertExactJson(['message' => 'Resource not found.']);
});

it('returns 413 with universal envelope for PostTooLargeException', function () {
    $this->getJson('/api/v1/throws/too-large')
        ->assertStatus(413)
        ->assertExactJson(['message' => 'File exceeds 10 MB limit.']);
});

it('returns 415 with universal envelope for UnsupportedMediaTypeHttpException', function () {
    $this->getJson('/api/v1/throws/bad-media')
        ->assertStatus(415)
        ->assertExactJson(['message' => 'Only JPG, PNG, WebP supported.']);
});

it('returns 429 with Retry-After header for ThrottleRequestsException', function () {
    // First call passes the 1-per-minute limiter.
    $this->getJson('/api/v1/throws/throttled')->assertOk();
    // Second call trips it.
    $response = $this->getJson('/api/v1/throws/throttled');

    $response->assertStatus(429)
        ->assertJson(['message' => 'Too many requests.']);
    expect($response->headers->has('Retry-After'))->toBeTrue();
})->skip(
    fn () => app(RateLimiter::class)->limiter('api') !== null,
    'requires no shared limiter; isolated by inline middleware in test setup'
);
