<?php

declare(strict_types=1);

use App\Filament\Resources\Albums\Pages\ListAlbums;
use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

test('ListPhotos page renders successfully', function () {
    $this->get(ListPhotos::getUrl())->assertOk();
});

test('ListAlbums page renders successfully', function () {
    $this->get(ListAlbums::getUrl())->assertOk();
});

test('ListTags page renders successfully', function () {
    $this->get(ListTags::getUrl())->assertOk();
});

test('dashboard renders successfully', function () {
    $this->get('/admin')->assertOk();
});

test('storage management page renders successfully', function () {
    $this->get('/admin/storage-management')->assertOk();
});
