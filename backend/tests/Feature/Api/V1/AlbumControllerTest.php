<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Middleware\ETag;
use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/albums', [AlbumController::class, 'index']);
        Route::get('/albums/{album}', [AlbumController::class, 'show'])->middleware(ETag::class);
    });
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/v1')->group(function () {
        Route::post('/albums', [AlbumController::class, 'store']);
        Route::patch('/albums/{album}', [AlbumController::class, 'update']);
        Route::delete('/albums/{album}', [AlbumController::class, 'destroy']);
    });
});

function albumHeaders(User $user, array $abilities = ['albums:write']): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('t', $abilities)->plainTextToken];
}

it('GET /albums returns cursor-paginated envelope with photos_count', function () {
    Album::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/albums?per_page=2');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'photos_count']], 'links', 'meta']);
});

it('GET /albums?sort=most_photos orders by photo count desc', function () {
    $a = Album::factory()->create();
    $b = Album::factory()->create();
    Photo::factory()->count(2)->create(['album_id' => $a->id]);
    Photo::factory()->count(5)->create(['album_id' => $b->id]);

    $ids = collect($this->getJson('/api/v1/albums?sort=most_photos')->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($b->id);
    expect($ids[1])->toBe($a->id);
});

it('GET /albums/{id} returns AlbumData envelope', function () {
    $album = Album::factory()->create();

    $this->getJson("/api/v1/albums/{$album->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $album->id);
});

it('GET /albums/{id} returns 304 on matching If-None-Match', function () {
    $album = Album::factory()->create();
    $etag = sprintf('W/"%s"', sha1((string) $album->updated_at));

    $this->withHeader('If-None-Match', $etag)
        ->getJson("/api/v1/albums/{$album->id}")
        ->assertStatus(304);
});

it('POST /albums creates and returns 201', function () {
    $user = User::factory()->create();

    $this->withHeaders(albumHeaders($user))
        ->postJson('/api/v1/albums', ['name' => 'Travel'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Travel');

    expect(Album::where('user_id', $user->id)->where('name', 'Travel')->exists())->toBeTrue();
});

it('POST /albums 422 on duplicate name for same user', function () {
    $user = User::factory()->create();
    Album::factory()->create(['user_id' => $user->id, 'name' => 'Travel']);

    $this->withHeaders(albumHeaders($user))
        ->postJson('/api/v1/albums', ['name' => 'Travel'])
        ->assertStatus(422);
});

it('POST /albums 403 without albums:write ability', function () {
    $user = User::factory()->create();

    $this->withHeaders(albumHeaders($user, abilities: ['photos:write']))
        ->postJson('/api/v1/albums', ['name' => 'Whatever'])
        ->assertStatus(403);
});

it('PATCH renames an album', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

    $this->withHeaders(albumHeaders($user))
        ->patchJson("/api/v1/albums/{$album->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('PATCH 403 from non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(albumHeaders($stranger))
        ->patchJson("/api/v1/albums/{$album->id}", ['name' => 'Hacked'])
        ->assertStatus(403);
});

it('DELETE returns 204 and detaches photos (album_id set null)', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id]);
    $photo = Photo::factory()->create(['album_id' => $album->id]);

    $this->withHeaders(albumHeaders($user))
        ->deleteJson("/api/v1/albums/{$album->id}")
        ->assertStatus(204);

    expect(Album::find($album->id))->toBeNull();
    expect($photo->fresh()->album_id)->toBeNull();
});

it('DELETE 403 from non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $owner->id]);

    $this->withHeaders(albumHeaders($stranger))
        ->deleteJson("/api/v1/albums/{$album->id}")
        ->assertStatus(403);
});
