<?php

declare(strict_types=1);

use App\Http\Requests\Album\StoreAlbumRequest;
use App\Http\Requests\Album\UpdateAlbumRequest;
use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function makeStoreAlbum(?User $user = null): StoreAlbumRequest
{
    $request = new StoreAlbumRequest;
    $request->setUserResolver(fn () => $user ?? User::factory()->create());

    return $request;
}

function makeUpdateAlbum(Album $album, ?User $user = null): UpdateAlbumRequest
{
    $request = new UpdateAlbumRequest;
    $request->setUserResolver(fn () => $user ?? $album->user);
    // Wire a dummy route with the album bound — UpdateAlbumRequest reads it for ignore().
    $route = (new Route(['PATCH'], 'albums/{album}', []))->bind(Request::create('/albums/'.$album->id, 'PATCH'));
    $route->setParameter('album', $album);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('accepts a valid POST /albums payload', function () {
    $user = User::factory()->create();
    $request = makeStoreAlbum($user);

    $v = Validator::make([
        'name' => 'Travel',
        'description' => 'Trips abroad',
    ], $request->rules());

    expect($v->fails())->toBeFalse();
});

it('rejects duplicate album name for the same user', function () {
    $user = User::factory()->create();
    Album::factory()->create(['user_id' => $user->id, 'name' => 'Travel']);

    $request = makeStoreAlbum($user);
    $v = Validator::make(['name' => 'Travel'], $request->rules());

    expect($v->errors()->has('name'))->toBeTrue();
});

it('allows the SAME album name for two different users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    Album::factory()->create(['user_id' => $userA->id, 'name' => 'Travel']);

    $request = makeStoreAlbum($userB);
    $v = Validator::make(['name' => 'Travel'], $request->rules());

    expect($v->fails())->toBeFalse();
});

it('rejects cover_photo_id pointing to a photo owned by someone else', function () {
    $authUser = User::factory()->create();
    $stranger = User::factory()->create();
    $foreignPhoto = Photo::factory()->create(['user_id' => $stranger->id]);

    $request = makeStoreAlbum($authUser);
    $v = Validator::make([
        'name' => 'Mine',
        'cover_photo_id' => $foreignPhoto->id,
    ], $request->rules());

    expect($v->errors()->has('cover_photo_id'))->toBeTrue();
});

it('PATCH /albums/{id} ignores the current row in the unique check', function () {
    $user = User::factory()->create();
    $album = Album::factory()->create(['user_id' => $user->id, 'name' => 'Vacation']);

    $request = makeUpdateAlbum($album, $user);
    $v = Validator::make(['name' => 'Vacation'], $request->rules());

    expect($v->fails())->toBeFalse();
});

it('PATCH /albums/{id} still rejects collision with a sibling album', function () {
    $user = User::factory()->create();
    Album::factory()->create(['user_id' => $user->id, 'name' => 'Family']);
    $albumB = Album::factory()->create(['user_id' => $user->id, 'name' => 'Vacation']);

    $request = makeUpdateAlbum($albumB, $user);
    $v = Validator::make(['name' => 'Family'], $request->rules());

    expect($v->errors()->has('name'))->toBeTrue();
});
