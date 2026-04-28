<?php

declare(strict_types=1);

use App\Models\Album;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets the photo owner view, update, and delete their own photo', function () {
    $owner = User::factory()->create();
    $photo = Photo::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $photo))->toBeTrue();
    expect($owner->can('update', $photo))->toBeTrue();
    expect($owner->can('delete', $photo))->toBeTrue();
});

it('denies a non-owner non-admin', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $photo = Photo::factory()->create(['user_id' => $owner->id]);

    expect($stranger->can('view', $photo))->toBeFalse();
    expect($stranger->can('update', $photo))->toBeFalse();
    expect($stranger->can('delete', $photo))->toBeFalse();
});

it('lets an admin act on any photo via the Gate::before bypass', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $photo = Photo::factory()->create(['user_id' => $owner->id]);

    expect($admin->can('view', $photo))->toBeTrue();
    expect($admin->can('update', $photo))->toBeTrue();
    expect($admin->can('delete', $photo))->toBeTrue();
});

it('applies the same rules to albums', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $album = Album::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('update', $album))->toBeTrue();
    expect($stranger->can('update', $album))->toBeFalse();
    expect($admin->can('delete', $album))->toBeTrue();
});
