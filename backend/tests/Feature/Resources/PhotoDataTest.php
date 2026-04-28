<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Http\Resources\PhotoData;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function renderPhotoData(Photo $photo, ?User $viewer = null): array
{
    return PhotoData::make($photo)
        ->response(Request::create(
            uri: '/api/v1/photos/'.$photo->id,
            method: 'GET'
        )->setUserResolver(fn () => $viewer))
        ->getData(true)['data'];
}

it('exposes the documented top-level keys', function () {
    $owner = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $tag = Tag::factory()->create();
    $photo->tags()->attach($tag);
    $photo->load(PhotoData::WITH);

    $payload = renderPhotoData($photo, $owner);

    expect($payload)->toHaveKeys([
        'id', 'title', 'description', 'filename', 'urls', 'width', 'height',
        'file_size', 'mime_type', 'is_favorite', 'exif', 'processing_status',
        'processing_error', 'tags', 'owner', 'created_at', 'updated_at',
    ]);
});

it('includes urls.original for the owner', function () {
    $owner = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $photo->load(PhotoData::WITH);

    $payload = renderPhotoData($photo, $owner);

    expect($payload['urls'])->toHaveKey('original');
});

it('excludes urls.original from a non-owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $photo->load(PhotoData::WITH);

    $payload = renderPhotoData($photo, $stranger);

    expect($payload['urls'])->not->toHaveKey('original');
});

it('includes urls.original for an admin viewing someone elses photo', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $photo = Photo::factory()->processed()->create(['user_id' => $owner->id]);
    $photo->load(PhotoData::WITH);

    $payload = renderPhotoData($photo, $admin);

    expect($payload['urls'])->toHaveKey('original');
});

it('hides width/height until processing completes', function () {
    $photo = Photo::factory()->create([
        'processing_status' => ProcessingStatus::Pending->value,
    ]);
    $photo->load(PhotoData::WITH);

    $payload = renderPhotoData($photo);

    expect($payload['width'])->toBeNull();
    expect($payload['height'])->toBeNull();
});

it('declares the eager-load WITH constant per CLAUDE.md DRY rule', function () {
    expect(PhotoData::WITH)->toContain('album:id,name', 'tags:id,name,slug', 'user:id,name');
});
