<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Tag;
use App\Services\TagAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates new tags from never-seen names', function () {
    $photo = Photo::factory()->create();

    (new TagAssigner)->syncByNames($photo, ['sunset', 'beach']);

    expect(Tag::count())->toBe(2);
    expect($photo->tags()->pluck('name')->sort()->values()->all())->toBe(['beach', 'sunset']);
});

it('reuses an existing tag with the same name', function () {
    $existing = Tag::factory()->create(['name' => 'beach', 'slug' => 'beach']);
    $photo = Photo::factory()->create();

    (new TagAssigner)->syncByNames($photo, ['beach']);

    expect(Tag::count())->toBe(1);
    expect($photo->tags()->pluck('id')->all())->toBe([$existing->id]);
});

it('resolves slug collisions with -2 / -3 suffixes', function () {
    Tag::create(['name' => 'Sunset', 'slug' => 'sunset']);
    Tag::create(['name' => 'SUNSET', 'slug' => 'sunset-2']);
    $photo = Photo::factory()->create();

    (new TagAssigner)->syncByNames($photo, ['sunset!']);

    $created = Tag::query()->where('name', 'sunset!')->first();
    expect($created)->not->toBeNull();
    expect($created->slug)->toBe('sunset-3');
});

it('replaces the previous tag set on each call (sync semantics)', function () {
    $photo = Photo::factory()->create();
    (new TagAssigner)->syncByNames($photo, ['sunset', 'beach']);
    expect($photo->tags()->count())->toBe(2);

    (new TagAssigner)->syncByNames($photo, ['mountain']);

    expect($photo->fresh()->tags()->pluck('name')->all())->toBe(['mountain']);
});

it('trims whitespace and skips empty names', function () {
    $photo = Photo::factory()->create();

    (new TagAssigner)->syncByNames($photo, ['  beach  ', '', '   ', 'sunset']);

    $names = $photo->tags()->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['beach', 'sunset']);
});

it('falls back to a base slug "tag" when Str::slug returns empty', function () {
    $photo = Photo::factory()->create();

    // A name composed entirely of non-slug chars produces an empty slug
    // from Str::slug; the assigner falls back to "tag" + collision suffix.
    (new TagAssigner)->syncByNames($photo, ['!!!', '???']);

    $slugs = Tag::pluck('slug')->sort()->values()->all();
    expect($slugs)->toBe(['tag', 'tag-2']);
});
