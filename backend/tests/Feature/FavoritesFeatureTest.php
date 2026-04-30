<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function authenticateUser(?User $user = null): User
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user, ['photos:write', 'albums:write']);

    return $user;
}

// --- Favorite / Unfavorite endpoints ---

test('authenticated user can favorite any photo', function () {
    $owner = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $user = authenticateUser();

    $response = $this->putJson("/api/v1/photos/{$photo->id}/favorite");

    $response->assertNoContent();
    $this->assertDatabaseHas('favorites', [
        'user_id' => $user->id,
        'photo_id' => $photo->id,
    ]);
});

test('favorite is idempotent — second call returns 204 with single row', function () {
    $photo = Photo::factory()->processed()->create();
    $user = authenticateUser();

    $this->putJson("/api/v1/photos/{$photo->id}/favorite")->assertNoContent();
    $this->putJson("/api/v1/photos/{$photo->id}/favorite")->assertNoContent();

    expect(
        \DB::table('favorites')
            ->where('user_id', $user->id)
            ->where('photo_id', $photo->id)
            ->count()
    )->toBe(1);
});

test('authenticated user can unfavorite a photo', function () {
    $photo = Photo::factory()->processed()->create();
    $user = authenticateUser();

    $user->favoritePhotos()->attach($photo->id);

    $response = $this->deleteJson("/api/v1/photos/{$photo->id}/favorite");

    $response->assertNoContent();
    $this->assertDatabaseMissing('favorites', [
        'user_id' => $user->id,
        'photo_id' => $photo->id,
    ]);
});

test('unfavorite is idempotent — returns 204 even if not favorited', function () {
    $photo = Photo::factory()->processed()->create();
    authenticateUser();

    $this->deleteJson("/api/v1/photos/{$photo->id}/favorite")->assertNoContent();
});

test('unauthenticated user cannot favorite', function () {
    $photo = Photo::factory()->processed()->create();

    $this->putJson("/api/v1/photos/{$photo->id}/favorite")->assertUnauthorized();
});

// --- is_favorite in photo response ---

test('GET /photos returns is_favorite=true for photos the user has favorited', function () {
    $user = authenticateUser();
    $photo = Photo::factory()->processed()->create();

    $user->favoritePhotos()->attach($photo->id);

    $response = $this->getJson('/api/v1/photos');

    $response->assertOk();
    $photoData = collect($response->json('data'))->firstWhere('id', $photo->id);
    expect($photoData['is_favorite'])->toBeTrue();
    expect($photoData['favorites_count'])->toBe(1);
});

test('GET /photos returns is_favorite=false for photos the user has NOT favorited', function () {
    authenticateUser();
    $photo = Photo::factory()->processed()->create();

    $response = $this->getJson('/api/v1/photos');

    $response->assertOk();
    $photoData = collect($response->json('data'))->firstWhere('id', $photo->id);
    expect($photoData['is_favorite'])->toBeFalse();
    expect($photoData['favorites_count'])->toBe(0);
});

test('GET /photos/{id} returns is_favorite=true when favorited', function () {
    $user = authenticateUser();
    $photo = Photo::factory()->processed()->create();

    $user->favoritePhotos()->attach($photo->id);

    $response = $this->getJson("/api/v1/photos/{$photo->id}");

    $response->assertOk();
    expect($response->json('data.is_favorite'))->toBeTrue();
    expect($response->json('data.favorites_count'))->toBe(1);
});

test('is_favorite is per-user — user A favorites, user B sees false', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $photo = Photo::factory()->processed()->create();

    $userA->favoritePhotos()->attach($photo->id);

    Sanctum::actingAs($userB, ['photos:write']);
    $response = $this->getJson("/api/v1/photos/{$photo->id}");

    expect($response->json('data.is_favorite'))->toBeFalse();
    expect($response->json('data.favorites_count'))->toBe(1);
});

test('favorites_count reflects total across all users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $photo = Photo::factory()->processed()->create();

    $userA->favoritePhotos()->attach($photo->id);
    $userB->favoritePhotos()->attach($photo->id);

    Sanctum::actingAs($userA, ['photos:write']);
    $response = $this->getJson("/api/v1/photos/{$photo->id}");

    expect($response->json('data.favorites_count'))->toBe(2);
    expect($response->json('data.is_favorite'))->toBeTrue();
});

// --- Favorites filter ---

test('GET /photos?favorites=1 returns only the current user favorites', function () {
    $user = authenticateUser();
    $favPhoto = Photo::factory()->processed()->create();
    $otherPhoto = Photo::factory()->processed()->create();

    $user->favoritePhotos()->attach($favPhoto->id);

    $response = $this->getJson('/api/v1/photos?favorites=1');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($favPhoto->id);
    expect($ids)->not->toContain($otherPhoto->id);
});

test('GET /photos?favorites=1 returns empty when user has no favorites', function () {
    authenticateUser();
    Photo::factory()->processed()->create();

    $response = $this->getJson('/api/v1/photos?favorites=1');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('GET /photos?favorites=1 without auth returns all photos (filter ignored)', function () {
    $owner = User::factory()->create();
    Photo::factory()->processed()->count(3)->create(['user_id' => $owner->id]);

    $response = $this->getJson('/api/v1/photos?favorites=1');

    $response->assertOk();
    // Without auth, favorites filter is no-op (userId is null)
    expect(count($response->json('data')))->toBe(3);
});

// --- Favorite then check response immediately ---

test('favorite then GET shows is_favorite=true in response', function () {
    $user = authenticateUser();
    $photo = Photo::factory()->processed()->create();

    $this->putJson("/api/v1/photos/{$photo->id}/favorite")->assertNoContent();

    $response = $this->getJson("/api/v1/photos/{$photo->id}");
    expect($response->json('data.is_favorite'))->toBeTrue();
});

test('unfavorite then GET shows is_favorite=false in response', function () {
    $user = authenticateUser();
    $photo = Photo::factory()->processed()->create();

    $user->favoritePhotos()->attach($photo->id);
    $this->deleteJson("/api/v1/photos/{$photo->id}/favorite")->assertNoContent();

    $response = $this->getJson("/api/v1/photos/{$photo->id}");
    expect($response->json('data.is_favorite'))->toBeFalse();
});
