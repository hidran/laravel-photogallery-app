<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TagController;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/tags', [TagController::class, 'index']);
    });
});

it('returns every tag with photos_count, count_desc default', function () {
    $popular = Tag::factory()->create(['name' => 'beach']);
    $rare = Tag::factory()->create(['name' => 'aurora']);
    $photo = Photo::factory()->create();
    $photo->tags()->attach($popular);

    $response = $this->getJson('/api/v1/tags');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    $first = $response->json('data.0');
    expect($first['name'])->toBe('beach');
    expect($first['photos_count'])->toBe(1);
    expect($names)->toContain('aurora');
});

it('?sort=name_asc orders alphabetically', function () {
    Tag::factory()->create(['name' => 'charlie']);
    Tag::factory()->create(['name' => 'alpha']);
    Tag::factory()->create(['name' => 'bravo']);

    $names = collect($this->getJson('/api/v1/tags?sort=name_asc')->json('data'))
        ->pluck('name')->all();

    expect($names)->toBe(['alpha', 'bravo', 'charlie']);
});

it('serves the page with a single SQL query', function () {
    Tag::factory()->count(5)->create();

    DB::enableQueryLog();
    $this->getJson('/api/v1/tags')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(1);
});
