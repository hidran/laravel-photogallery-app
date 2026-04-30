<?php

declare(strict_types=1);

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * DESIGN.md §6.7 + §10.3 — exhaustive 401/403 sweep against the canonical
 * /api/v1 routes registered by T039. For each mutating endpoint we assert:
 *  - no token → 401 with universal envelope
 *  - cross-user token → 403 with universal envelope
 *
 * Routes that don't bind a model (POST /albums) only have the 401 case.
 */
function authedHeaders(User $user, array $abilities = ['photos:write', 'albums:write']): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('test', $abilities)->plainTextToken];
}

dataset('mutating_endpoints_with_owner_resource', function () {
    return [
        'PATCH photo' => function () {
            $owner = User::factory()->create();
            $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

            return ['method' => 'patchJson', 'url' => "/api/v1/photos/{$photo->id}", 'body' => ['title' => 'x'], 'owner' => $owner];
        },
        'DELETE photo' => function () {
            $owner = User::factory()->create();
            $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

            return ['method' => 'deleteJson', 'url' => "/api/v1/photos/{$photo->id}", 'body' => [], 'owner' => $owner];
        },
        'PATCH album' => function () {
            $owner = User::factory()->create();
            $album = Album::factory()->create(['user_id' => $owner->id]);

            return ['method' => 'patchJson', 'url' => "/api/v1/albums/{$album->id}", 'body' => ['name' => 'x'], 'owner' => $owner];
        },
        'DELETE album' => function () {
            $owner = User::factory()->create();
            $album = Album::factory()->create(['user_id' => $owner->id]);

            return ['method' => 'deleteJson', 'url' => "/api/v1/albums/{$album->id}", 'body' => [], 'owner' => $owner];
        },
    ];
});

it('returns 401 + universal envelope when no token is sent', function (Closure $factory) {
    $config = $factory();

    $this->{$config['method']}($config['url'], $config['body'])
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
})->with('mutating_endpoints_with_owner_resource');

it('returns 403 + universal envelope when token belongs to a different user', function (Closure $factory) {
    $config = $factory();
    $stranger = User::factory()->create();

    $this->withHeaders(authedHeaders($stranger))
        ->{$config['method']}($config['url'], $config['body'])
        ->assertStatus(403)
        ->assertExactJson(['message' => 'This action is unauthorized.']);
})->with('mutating_endpoints_with_owner_resource');

it('POST /albums returns 401 without a token', function () {
    $this->postJson('/api/v1/albums', ['name' => 'x'])
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('POST /auth/logout returns 401 without a token', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401);
});

it('GET /auth/me returns 401 without a token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('admin token bypasses ownership on every covered endpoint', function (Closure $factory) {
    $config = $factory();
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withHeaders(authedHeaders($admin, ['photos:write', 'albums:write', 'admin']))
        ->{$config['method']}($config['url'], $config['body']);

    // Tightened per PR #4 review C5: must land on a documented success
    // status from §6.7. "not 403 and not 401" false-passed on 5xx.
    expect($response->status())->toBeIn([200, 201, 204]);
})->with('mutating_endpoints_with_owner_resource');
