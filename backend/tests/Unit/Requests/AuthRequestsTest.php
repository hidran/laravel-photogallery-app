<?php

declare(strict_types=1);

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateRegister(array $payload): Illuminate\Validation\Validator
{
    return Validator::make($payload, (new RegisterRequest)->rules());
}

function validateLogin(array $payload): Illuminate\Validation\Validator
{
    return Validator::make($payload, (new LoginRequest)->rules());
}

it('accepts a valid registration payload', function () {
    $v = validateRegister([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    expect($v->fails())->toBeFalse();
});

it('rejects too-short names', function () {
    $v = validateRegister([
        'name' => 'A',
        'email' => 'a@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('name'))->toBeTrue();
});

it('rejects passwords shorter than 8 chars', function () {
    $v = validateRegister([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('password'))->toBeTrue();
});

it('rejects unconfirmed passwords', function () {
    $v = validateRegister([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'other-password',
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('password'))->toBeTrue();
});

it('rejects duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $v = validateRegister([
        'name' => 'Second',
        'email' => 'taken@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('email'))->toBeTrue();
});

it('accepts a valid login payload', function () {
    $v = validateLogin([
        'email' => 'jane@example.com',
        'password' => 'anything-non-empty',
    ]);

    expect($v->fails())->toBeFalse();
});

it('login requires email', function () {
    $v = validateLogin(['password' => 'x']);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('email'))->toBeTrue();
});

it('login requires password', function () {
    $v = validateLogin(['email' => 'jane@example.com']);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('password'))->toBeTrue();
});

it('login accepts optional device_name', function () {
    $v = validateLogin([
        'email' => 'jane@example.com',
        'password' => 'x',
        'device_name' => 'Pixel 9',
    ]);

    expect($v->fails())->toBeFalse();
});
