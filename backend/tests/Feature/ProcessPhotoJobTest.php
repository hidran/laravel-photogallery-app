<?php

declare(strict_types=1);

use App\Contracts\ExifReader;
use App\Contracts\ImageProcessor;
use App\Enums\ProcessingStatus;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('photos');
    Storage::fake('photos_private');
});

test('ProcessPhoto job transitions status to completed', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);
    Storage::disk('photos_private')->putFileAs('originals', $file, 'test.jpg');

    $photo = Photo::factory()->create([
        'user_id' => $user->id,
        'original_path' => 'originals/test.jpg',
        'filename' => 'test.jpg',
        'processing_status' => ProcessingStatus::Pending,
    ]);

    ProcessPhoto::dispatchSync($photo);

    $photo->refresh();
    expect($photo->processing_status)->toBe(ProcessingStatus::Completed);
    expect($photo->thumbnail_path)->not->toBeNull();
    expect($photo->medium_path)->not->toBeNull();
    expect($photo->large_path)->not->toBeNull();
    expect($photo->width)->toBeGreaterThan(0);
    expect($photo->height)->toBeGreaterThan(0);
});

test('ProcessPhoto job sets status to failed on error', function () {
    $user = User::factory()->create();

    $photo = Photo::factory()->create([
        'user_id' => $user->id,
        'original_path' => 'originals/nonexistent.jpg',
        'filename' => 'nonexistent.jpg',
        'processing_status' => ProcessingStatus::Pending,
    ]);

    $job = new ProcessPhoto($photo);

    try {
        $job->handle(
            app(ImageProcessor::class),
            app(ExifReader::class),
        );
    } catch (Throwable $e) {
        $job->failed($e);
    }

    $photo->refresh();
    expect($photo->processing_status)->toBe(ProcessingStatus::Failed);
    expect($photo->processing_error)->not->toBeNull();
});

test('ProcessPhoto job has correct retry config', function () {
    $user = User::factory()->create();
    $photo = Photo::factory()->create(['user_id' => $user->id]);

    $job = new ProcessPhoto($photo);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([10, 30, 60]);
});
