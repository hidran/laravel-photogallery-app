<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PhotoController;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
        Route::put('/photos/{photo}/favorite', [PhotoController::class, 'favorite']);
        Route::delete('/photos/{photo}/favorite', [PhotoController::class, 'unfavorite']);
    });
});

function favHeaders(User $user, array $abilities = ['photos:write']): array
{
    $token = $user->createToken('test', $abilities)->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

it('PUT /favorite marks the photo as favorite (204)', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id, 'is_favorite' => false]);

    $this->withHeaders(favHeaders($user))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($photo->fresh()->is_favorite)->toBeTrue();
});

it('PUT /favorite is idempotent — repeated calls still 204 with no flip', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id, 'is_favorite' => true]);

    $this->withHeaders(favHeaders($user))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($photo->fresh()->is_favorite)->toBeTrue();
});

it('DELETE /favorite unmarks (204)', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id, 'is_favorite' => true]);

    $this->withHeaders(favHeaders($user))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($photo->fresh()->is_favorite)->toBeFalse();
});

it('DELETE /favorite is idempotent — repeated calls still 204', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id, 'is_favorite' => false]);

    $this->withHeaders(favHeaders($user))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($photo->fresh()->is_favorite)->toBeFalse();
});

it('PUT /favorite 403 from non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

    $this->withHeaders(favHeaders($stranger))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(403)
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

it('DELETE /favorite 403 from non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id, 'is_favorite' => true]);

    $this->withHeaders(favHeaders($stranger))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(403);
});

it('PUT /favorite 404 for missing UUID', function () {
    $user = User::factory()->create();
    $this->withHeaders(favHeaders($user))
        ->putJson('/api/v1/photos/00000000-0000-7000-8000-000000000000/favorite')
        ->assertStatus(404);
});
