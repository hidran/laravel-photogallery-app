<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a Sanctum token for a UUID-keyed user without insert errors', function () {
    $user = User::factory()->create();

    $abilities = [
        TokenAbility::PhotosWrite->value,
        TokenAbility::AlbumsWrite->value,
    ];

    $newAccessToken = $user->createToken('regression-test', $abilities);

    expect($newAccessToken->plainTextToken)->toBeString()->not->toBeEmpty();
    expect($newAccessToken->accessToken->tokenable_id)->toBe($user->id);
    expect($newAccessToken->accessToken->can(TokenAbility::PhotosWrite->value))->toBeTrue();
    expect($newAccessToken->accessToken->can(TokenAbility::Admin->value))->toBeFalse();
});
