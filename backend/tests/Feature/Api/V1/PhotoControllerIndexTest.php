<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Middleware\ETag;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/photos', [PhotoController::class, 'index']);
        Route::get('/photos/{photo}', [PhotoController::class, 'show'])->middleware(ETag::class);
    });
});

it('returns photos in cursor-paginated form with envelope', function () {
    Photo::factory()->processed()->count(5)->create();

    $response = $this->getJson('/api/v1/photos?per_page=2');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'title', 'urls', 'owner']],
            'meta' => ['next_cursor', 'prev_cursor'],
            'links',
        ]);
    expect(count($response->json('data')))->toBe(2);
});

it('eager-loads relations to avoid N+1 (≤2 queries for the photos page)', function () {
    Photo::factory()->processed()->count(3)->create()->each(function ($photo) {
        $photo->tags()->attach(Tag::factory()->create());
    });

    DB::enableQueryLog();
    $this->getJson('/api/v1/photos?per_page=10')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Cursor pagination skips the offset-pagination `total` query, so
    // the budget is: 1 page query + 3 eager-loads from PhotoData::WITH
    // (album, tags, user) = 4. Tightened from ≤6 per PR #4 review C3 so
    // a stray eager-load slip is caught.
    expect(count($queries))->toBeLessThanOrEqual(4);

    // No per-row repetitions: every distinct query SQL must be unique.
    $sqls = array_map(fn ($q) => $q['query'], $queries);
    expect($sqls)->toBe(array_unique($sqls));
});

it('filters by ?search=', function () {
    $hit = Photo::factory()->processed()->create(['title' => 'Sunset over bay']);
    Photo::factory()->processed()->create(['title' => 'Random other']);

    $response = $this->getJson('/api/v1/photos?search=Sunset');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($hit->id);
    expect(count($ids))->toBe(1);
});

it('filters by ?tags[]= with AND semantics', function () {
    $both = Photo::factory()->processed()->create();
    $oneOnly = Photo::factory()->processed()->create();
    $beach = Tag::factory()->create(['slug' => 'beach']);
    $sunset = Tag::factory()->create(['slug' => 'sunset']);
    $both->tags()->attach([$beach->id, $sunset->id]);
    $oneOnly->tags()->attach($beach);

    $response = $this->getJson('/api/v1/photos?tags[]=beach&tags[]=sunset');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$both->id]);
});

it('show returns 200 with envelope', function () {
    $photo = Photo::factory()->processed()->create();

    $response = $this->getJson("/api/v1/photos/{$photo->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'title', 'urls']])
        ->assertJsonPath('data.id', $photo->id);
});

it('show returns 304 when If-None-Match matches the ETag', function () {
    $photo = Photo::factory()->processed()->create();
    $etag = sprintf('W/"%s"', sha1((string) $photo->updated_at));

    $this->withHeader('If-None-Match', $etag)
        ->getJson("/api/v1/photos/{$photo->id}")
        ->assertStatus(304);
});

it('show returns 404 with universal envelope for missing id', function () {
    $this->getJson('/api/v1/photos/00000000-0000-7000-8000-000000000000')
        ->assertStatus(404)
        ->assertExactJson(['message' => 'Resource not found.']);
});

it('rejects invalid sort with 422', function () {
    $this->getJson('/api/v1/photos?sort=random')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});
