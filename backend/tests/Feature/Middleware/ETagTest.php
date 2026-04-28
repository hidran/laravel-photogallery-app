<?php

declare(strict_types=1);

use App\Http\Middleware\ETag;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['api', ETag::class])->prefix('api/v1')->group(function () {
        Route::get('/photos/{photo}', fn (Photo $photo) => response()->json(['data' => ['id' => $photo->id]]));
        Route::get('/photos', fn () => response()->json(['data' => []]));
    });
});

it('emits a weak ETag header derived from updated_at on single-resource GET', function () {
    $photo = Photo::factory()->create();

    $response = $this->getJson("/api/v1/photos/{$photo->id}");

    $response->assertOk();
    $expected = sprintf('W/"%s"', sha1((string) $photo->updated_at));
    expect($response->headers->get('ETag'))->toBe($expected);
});

it('returns 304 with empty body when If-None-Match matches', function () {
    $photo = Photo::factory()->create();
    $etag = sprintf('W/"%s"', sha1((string) $photo->updated_at));

    $response = $this->withHeaders(['If-None-Match' => $etag])
        ->getJson("/api/v1/photos/{$photo->id}");

    $response->assertStatus(304);
    expect($response->getContent())->toBe('');
    expect($response->headers->get('ETag'))->toBe($etag);
});

it('serves 200 with body when If-None-Match does not match', function () {
    $photo = Photo::factory()->create();

    $response = $this->withHeaders(['If-None-Match' => 'W/"stale-hash"'])
        ->getJson("/api/v1/photos/{$photo->id}");

    $response->assertOk()->assertJsonPath('data.id', $photo->id);
});

it('skips ETag on list endpoints (no single bound model)', function () {
    $response = $this->getJson('/api/v1/photos');

    $response->assertOk();
    expect($response->headers->has('ETag'))->toBeFalse();
});
