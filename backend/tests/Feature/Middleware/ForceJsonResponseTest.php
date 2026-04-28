<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register a probe route under the same /api/v1 prefix so the api
    // middleware group (incl. ForceJsonResponse) actually runs.
    Route::middleware('api')->prefix('api/v1')->get(
        '/middleware-probe',
        fn () => response()->json([
            'accept' => request()->header('Accept'),
        ])
    );
});

it('rewrites Accept to application/json on /api/v1/* requests', function () {
    $response = $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/v1/middleware-probe');

    $response->assertOk()
        ->assertJson(['accept' => 'application/json']);
});

it('still applies when no Accept header is sent', function () {
    $response = $this->get('/api/v1/middleware-probe');

    $response->assertOk()
        ->assertJson(['accept' => 'application/json']);
});
