<?php

declare(strict_types=1);

use App\Http\Requests\Photo\IndexPhotosRequest;
use App\Http\Requests\Photo\StorePhotosRequest;
use App\Http\Requests\Photo\UpdatePhotoRequest;
use App\Models\Album;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateIndex(array $payload): Illuminate\Validation\Validator
{
    return Validator::make($payload, (new IndexPhotosRequest)->rules());
}

function makeStoreRequest(?User $user = null): StorePhotosRequest
{
    $request = new StorePhotosRequest;
    $request->setUserResolver(fn () => $user ?? User::factory()->create());

    return $request;
}

function validateStore(StorePhotosRequest $request, array $payload, array $files = []): Illuminate\Validation\Validator
{
    $merged = array_merge($payload, $files);

    return Validator::make($merged, $request->rules());
}

function makeUpdateRequest(?User $user = null): UpdatePhotoRequest
{
    $request = new UpdatePhotoRequest;
    $request->setUserResolver(fn () => $user ?? User::factory()->create());

    return $request;
}

it('accepts a sane GET /photos query', function () {
    $tag = Tag::factory()->create();
    $album = Album::factory()->create();

    $v = validateIndex([
        'search' => 'sunset',
        'tags' => [$tag->slug],
        'album_id' => $album->id,
        'favorites' => '1',
        'sort' => 'created_at',
        'order' => 'desc',
        'per_page' => 24,
    ]);

    expect($v->fails())->toBeFalse();
});

it('rejects an invalid sort value on GET /photos', function () {
    $v = validateIndex(['sort' => 'random']);
    expect($v->errors()->has('sort'))->toBeTrue();
});

it('rejects per_page outside 1-100 on GET /photos', function () {
    expect(validateIndex(['per_page' => 0])->errors()->has('per_page'))->toBeTrue();
    expect(validateIndex(['per_page' => 101])->errors()->has('per_page'))->toBeTrue();
});

it('rejects unknown tag slugs on GET /photos', function () {
    $v = validateIndex(['tags' => ['no-such-tag']]);
    expect($v->errors()->has('tags.0'))->toBeTrue();
});

it('accepts a valid POST /photos payload (1 file, no tags)', function () {
    $request = makeStoreRequest();
    $payload = [
        'files' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
    ];

    $v = validateStore($request, $payload);
    expect($v->fails())->toBeFalse();
});

it('rejects POST /photos with zero files', function () {
    $request = makeStoreRequest();
    $v = validateStore($request, ['files' => []]);
    expect($v->errors()->has('files'))->toBeTrue();
});

it('rejects POST /photos with more than 20 files', function () {
    $files = collect(range(1, 21))->map(fn ($i) => UploadedFile::fake()->image("$i.jpg"))->all();
    $request = makeStoreRequest();
    $v = validateStore($request, ['files' => $files]);
    expect($v->errors()->has('files'))->toBeTrue();
});

it('rejects POST /photos files larger than 10 MB', function () {
    $big = UploadedFile::fake()->create('big.jpg', 10241, 'image/jpeg');
    $request = makeStoreRequest();
    $v = validateStore($request, ['files' => [$big]]);
    expect($v->errors()->has('files.0'))->toBeTrue();
});

it('rejects POST /photos with non-image MIME', function () {
    $bad = UploadedFile::fake()->create('a.pdf', 100, 'application/pdf');
    $request = makeStoreRequest();
    $v = validateStore($request, ['files' => [$bad]]);
    expect($v->errors()->has('files.0'))->toBeTrue();
});

it('rejects POST /photos when album_id is not owned by the authed user', function () {
    $authUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $foreignAlbum = Album::factory()->create(['user_id' => $otherUser->id]);

    $request = makeStoreRequest($authUser);
    $v = validateStore($request, [
        'files' => [UploadedFile::fake()->image('a.jpg')],
        'album_id' => $foreignAlbum->id,
    ]);

    expect($v->errors()->has('album_id'))->toBeTrue();
});

it('PATCH /photos/{id} requires at least one supported field', function () {
    $request = makeUpdateRequest();
    $v = Validator::make([], $request->rules());
    expect($v->errors()->has('title'))->toBeTrue();
});

it('PATCH /photos/{id} accepts a single field', function () {
    $request = makeUpdateRequest();
    $v = Validator::make(['is_favorite' => true], $request->rules());
    expect($v->fails())->toBeFalse();
});
