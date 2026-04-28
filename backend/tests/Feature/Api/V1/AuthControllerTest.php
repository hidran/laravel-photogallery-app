<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->middleware('auth:sanctum');
    });
});

it('registers a new user and returns 201 with a bearer token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'is_admin']],
        ])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'jane@example.com');

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('rejects registration with 422 on validation errors', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('logs in a valid user and issues a fresh token', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret-password',
        'device_name' => 'Pixel 9',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.id', $user->id);

    expect($user->tokens()->where('name', 'Pixel 9')->exists())->toBeTrue();
});

it('returns 401 with universal envelope on bad credentials', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns 422 on missing login fields', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('logout deletes the current access token and returns 204', function () {
    $user = User::factory()->create();
    $token = $user->createToken('dev', ['photos:write'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(204);
    expect($user->tokens()->count())->toBe(0);
});

it('logout returns 401 without a token', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('me returns the authed user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('dev', ['photos:write'])->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('grants the admin token ability only to admin users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('p')]);
    $regular = User::factory()->create(['is_admin' => false, 'password' => bcrypt('p')]);

    $adminLogin = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'p']);
    $regularLogin = $this->postJson('/api/v1/auth/login', ['email' => $regular->email, 'password' => 'p']);

    $adminToken = $admin->tokens()->latest()->first();
    $regularToken = $regular->tokens()->latest()->first();

    expect($adminToken->can(TokenAbility::Admin->value))->toBeTrue();
    expect($regularToken->can(TokenAbility::Admin->value))->toBeFalse();
    expect($regularToken->can(TokenAbility::PhotosWrite->value))->toBeTrue();
});
