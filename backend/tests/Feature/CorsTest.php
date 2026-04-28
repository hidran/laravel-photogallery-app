<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/cors-probe', fn () => response()->json(['ok' => true]));
    });
});

it('config scopes CORS to api/* and pulls the allowlist from FRONTEND_URL', function () {
    expect(config('cors.paths'))->toBe(['api/*']);
    expect(config('cors.supports_credentials'))->toBeFalse();
    expect(config('cors.allowed_origins'))->not->toContain('*');
});

it('emits Access-Control-Allow-Origin equal to FRONTEND_URL when matched', function () {
    config(['cors.allowed_origins' => ['http://localhost:5173']]);

    $response = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Accept' => 'application/json',
    ])->get('/api/v1/cors-probe');

    $response->assertOk();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost:5173');
});

it('refuses to echo a non-allowlisted origin', function () {
    config(['cors.allowed_origins' => ['http://localhost:5173']]);

    $response = $this->withHeaders([
        'Origin' => 'https://evil.example.com',
        'Accept' => 'application/json',
    ])->get('/api/v1/cors-probe');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example.com');
});
