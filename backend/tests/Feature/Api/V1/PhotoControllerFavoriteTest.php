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

it('PUT /favorite marks the photo as favorite for the user (204)', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $this->withHeaders(favHeaders($user))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($user->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeTrue();
});

it('PUT /favorite is idempotent — repeated calls still 204 with no duplicate pivot', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);
    $user->favoritePhotos()->attach($photo->id);

    $this->withHeaders(favHeaders($user))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($user->favoritePhotos()->where('photo_id', $photo->id)->count())->toBe(1);
});

it('DELETE /favorite unmarks (204)', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);
    $user->favoritePhotos()->attach($photo->id);

    $this->withHeaders(favHeaders($user))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($user->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeFalse();
});

it('DELETE /favorite is idempotent — repeated calls still 204', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $this->withHeaders(favHeaders($user))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($user->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeFalse();
});

it('PUT /favorite allows non-owner to favorite (204)', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

    $this->withHeaders(favHeaders($stranger))
        ->putJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($stranger->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeTrue();
    // Owner's favorites should be unaffected.
    expect($owner->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeFalse();
});

it('DELETE /favorite allows non-owner to unfavorite (204)', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $stranger->favoritePhotos()->attach($photo->id);

    $this->withHeaders(favHeaders($stranger))
        ->deleteJson("/api/v1/photos/{$photo->id}/favorite")
        ->assertStatus(204);

    expect($stranger->favoritePhotos()->where('photo_id', $photo->id)->exists())->toBeFalse();
});

it('PUT /favorite 404 for missing UUID', function () {
    $user = User::factory()->create();
    $this->withHeaders(favHeaders($user))
        ->putJson('/api/v1/photos/00000000-0000-7000-8000-000000000000/favorite')
        ->assertStatus(404);
});
