<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PhotoController;
use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
        Route::patch('/photos/{photo}', [PhotoController::class, 'update']);
        Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    });
});

function authHeaderFor(User $user, array $abilities = ['photos:write', 'albums:write']): array
{
    $token = $user->createToken('test', $abilities)->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

it('PATCH updates the photo title and returns the resource envelope', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $response = $this->withHeaders(authHeaderFor($user))
        ->patchJson("/api/v1/photos/{$photo->id}", ['title' => 'Renamed']);

    $response->assertOk()->assertJsonPath('data.title', 'Renamed');
    expect($photo->fresh()->title)->toBe('Renamed');
});

it('PATCH replaces the tag set via TagAssigner', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);
    $existing = Tag::factory()->create(['name' => 'beach', 'slug' => 'beach']);
    $photo->tags()->attach($existing);

    $this->withHeaders(authHeaderFor($user))
        ->patchJson("/api/v1/photos/{$photo->id}", [
            'tags' => [],
            'new_tags' => ['mountain'],
        ])
        ->assertOk();

    expect($photo->fresh()->tags()->pluck('name')->all())->toBe(['mountain']);
});

it('PATCH bumps updated_at when only tags change (so ETag invalidates)', function () {
    // PR #4 review C2 — pivot writes don't bump updated_at on their own,
    // so a tag-only PATCH must explicitly touch() the photo.
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create([
        'user_id' => $user->id,
        'updated_at' => now()->subHour(),
    ]);
    $before = $photo->updated_at->copy();

    $this->withHeaders(authHeaderFor($user))
        ->patchJson("/api/v1/photos/{$photo->id}", ['new_tags' => ['mountain']])
        ->assertOk();

    expect($photo->fresh()->updated_at->greaterThan($before))->toBeTrue();
});

it('PATCH album_id requires the album to be owned by the authed user', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $foreignAlbum = Album::factory()->create(['user_id' => $stranger->id]);
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $this->withHeaders(authHeaderFor($user))
        ->patchJson("/api/v1/photos/{$photo->id}", ['album_id' => $foreignAlbum->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['album_id']);
});

it('PATCH 403 when called on someone else\'s photo', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

    $this->withHeaders(authHeaderFor($stranger))
        ->patchJson("/api/v1/photos/{$photo->id}", ['title' => 'Hacked'])
        ->assertStatus(403)
        ->assertExactJson(['message' => 'This action is unauthorized.']);
});

it('PATCH 403 when token lacks photos:write ability', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $this->withHeaders(authHeaderFor($user, abilities: ['albums:write']))
        ->patchJson("/api/v1/photos/{$photo->id}", ['title' => 'Renamed'])
        ->assertStatus(403);
});

it('PATCH admin can edit anyone\'s photo via Gate::before bypass', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

    $this->withHeaders(authHeaderFor($admin, abilities: ['photos:write', 'admin']))
        ->patchJson("/api/v1/photos/{$photo->id}", ['title' => 'Mod-edit'])
        ->assertOk();
});

it('DELETE returns 204 and removes the photo', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $user->id]);

    $this->withHeaders(authHeaderFor($user))
        ->deleteJson("/api/v1/photos/{$photo->id}")
        ->assertStatus(204);

    expect(Photo::find($photo->id))->toBeNull();
});

it('DELETE 403 from non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);

    $this->withHeaders(authHeaderFor($stranger))
        ->deleteJson("/api/v1/photos/{$photo->id}")
        ->assertStatus(403);
});
