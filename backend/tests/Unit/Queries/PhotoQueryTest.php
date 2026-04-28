<?php

declare(strict_types=1);

use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use App\Queries\PhotoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns all photos when no filters applied', function () {
    Photo::factory()->count(3)->create();

    $page = PhotoQuery::for()->paginate(null, 10);

    expect($page->count())->toBe(3);
});

it('filters by search term (LIKE on SQLite)', function () {
    $hit = Photo::factory()->create(['title' => 'Sunset over the bay']);
    Photo::factory()->create(['title' => 'Random other thing']);

    $page = PhotoQuery::for()->withSearch('Sunset')->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$hit->id]);
});

it('filters by single tag slug', function () {
    $hit = Photo::factory()->create();
    $miss = Photo::factory()->create();
    $tag = Tag::factory()->create(['slug' => 'sunset']);
    $hit->tags()->attach($tag);

    $page = PhotoQuery::for()->withTags(['sunset'])->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$hit->id]);
});

it('AND logic on multiple tags — photo must have every requested tag', function () {
    $both = Photo::factory()->create();
    $oneOnly = Photo::factory()->create();
    $sunset = Tag::factory()->create(['slug' => 'sunset']);
    $beach = Tag::factory()->create(['slug' => 'beach']);

    $both->tags()->attach([$sunset->id, $beach->id]);
    $oneOnly->tags()->attach($sunset);

    $page = PhotoQuery::for()->withTags(['sunset', 'beach'])->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$both->id]);
});

it('filters by album_id', function () {
    $album = Album::factory()->create();
    $inAlbum = Photo::factory()->create(['album_id' => $album->id]);
    Photo::factory()->create(['album_id' => null]);

    $page = PhotoQuery::for()->withAlbum($album->id)->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$inAlbum->id]);
});

it('filters by favorites only', function () {
    $fav = Photo::factory()->create(['is_favorite' => true]);
    Photo::factory()->create(['is_favorite' => false]);

    $page = PhotoQuery::for()->withFavorites(true)->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$fav->id]);
});

it('filters by owner via withOwner', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $alicePhoto = Photo::factory()->create(['user_id' => $alice->id]);
    Photo::factory()->create(['user_id' => $bob->id]);

    $page = PhotoQuery::for()->withOwner($alice->id)->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$alicePhoto->id]);
});

it('orders by created_at desc by default', function () {
    $oldest = Photo::factory()->create(['created_at' => now()->subDays(2)]);
    $middle = Photo::factory()->create(['created_at' => now()->subDay()]);
    $newest = Photo::factory()->create(['created_at' => now()]);

    $page = PhotoQuery::for()->applySort(null, null)->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$newest->id, $middle->id, $oldest->id]);
});

it('orders by title asc when requested', function () {
    Photo::factory()->create(['title' => 'Charlie']);
    Photo::factory()->create(['title' => 'Alpha']);
    Photo::factory()->create(['title' => 'Bravo']);

    $page = PhotoQuery::for()->applySort('title', 'asc')->paginate(null, 10);

    expect($page->pluck('title')->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('puts favorites first when sort=favorites', function () {
    $unfav = Photo::factory()->create(['is_favorite' => false, 'created_at' => now()]);
    $fav = Photo::factory()->create(['is_favorite' => true, 'created_at' => now()->subDay()]);

    $page = PhotoQuery::for()->applySort('favorites', 'desc')->paginate(null, 10);

    expect($page->pluck('id')->first())->toBe($fav->id);
    expect($page->pluck('id')->all())->toContain($unfav->id);
});

it('combines multiple filters', function () {
    $alice = User::factory()->create();
    $tag = Tag::factory()->create(['slug' => 'beach']);

    $hit = Photo::factory()->create([
        'user_id' => $alice->id,
        'is_favorite' => true,
        'title' => 'Sunny beach day',
    ]);
    $hit->tags()->attach($tag);

    Photo::factory()->create(['user_id' => $alice->id, 'is_favorite' => false]);

    $page = PhotoQuery::for()
        ->withOwner($alice->id)
        ->withFavorites(true)
        ->withTags(['beach'])
        ->withSearch('beach')
        ->applySort('created_at', 'desc')
        ->paginate(null, 10);

    expect($page->pluck('id')->all())->toBe([$hit->id]);
});
