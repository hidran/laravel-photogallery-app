<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Filament\Resources\Photos\Pages\CreatePhoto;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('photos_private');
    Queue::fake();
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

test('admin can create a photo with all required fields populated', function () {
    $file = UploadedFile::fake()->image('sunset.jpg', 800, 600);

    Livewire::test(CreatePhoto::class)
        ->fillForm([
            'original_path' => [$file],
            'title' => 'Sunset over the bay',
            'description' => 'A beautiful sunset',
            'is_favorite' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $photo = Photo::query()->first();
    expect($photo)->not->toBeNull();
    expect($photo->user_id)->toBe($this->admin->id);
    expect($photo->title)->toBe('Sunset over the bay');
    expect($photo->filename)->not->toBeNull()->not->toBeEmpty();
    expect($photo->original_path)->not->toBeNull()->not->toBeEmpty();
    expect($photo->processing_status)->toBe(ProcessingStatus::Pending);
});

test('created photo filename is derived from the uploaded file path', function () {
    $file = UploadedFile::fake()->image('beach-day.jpg', 800, 600);

    Livewire::test(CreatePhoto::class)
        ->fillForm([
            'original_path' => [$file],
            'title' => 'Beach Day',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $photo = Photo::query()->first();
    expect($photo->filename)->toEndWith('.jpg');
    expect($photo->original_path)->toStartWith('originals/');
});

test('created photo gets assigned to the authenticated admin user', function () {
    $file = UploadedFile::fake()->image('test.png', 100, 100);

    Livewire::test(CreatePhoto::class)
        ->fillForm([
            'original_path' => [$file],
            'title' => 'Test Photo',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $photo = Photo::query()->first();
    expect($photo->user_id)->toBe($this->admin->id);
});
