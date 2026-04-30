<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Models\Photo;
use App\Services\Imaging\InterventionImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('photos');
    Storage::fake('photos_private');
});

it('generates three variant images and updates the photo model', function (): void {
    // Arrange: create a small test image and store it on the private disk
    $fakeImage = UploadedFile::fake()->image('test.jpg', 200, 100);
    Storage::disk('photos_private')->put(
        'originals/test.jpg',
        $fakeImage->getContent(),
    );

    $photo = Photo::factory()->create([
        'filename' => 'test.jpg',
        'original_path' => 'originals/test.jpg',
        'width' => null,
        'height' => null,
        'thumbnail_path' => null,
        'medium_path' => null,
        'large_path' => null,
        'processing_status' => ProcessingStatus::Pending,
    ]);

    // Act
    $processor = app(InterventionImageProcessor::class);
    $processor->generate($photo);

    // Assert: photo model updated with variant paths
    $photo->refresh();

    expect($photo->thumbnail_path)->toBe("thumbnail/{$photo->id}.jpg");
    expect($photo->medium_path)->toBe("medium/{$photo->id}.jpg");
    expect($photo->large_path)->toBe("large/{$photo->id}.jpg");

    // Assert: width/height set (no upscaling — original is 200x100)
    expect($photo->width)->toBe(200);
    expect($photo->height)->toBe(100);

    // Assert: files exist on the public photos disk
    Storage::disk('photos')->assertExists("thumbnail/{$photo->id}.jpg");
    Storage::disk('photos')->assertExists("medium/{$photo->id}.jpg");
    Storage::disk('photos')->assertExists("large/{$photo->id}.jpg");
});

it('does not upscale images smaller than variant width', function (): void {
    $fakeImage = UploadedFile::fake()->image('small.jpg', 150, 75);
    Storage::disk('photos_private')->put(
        'originals/small.jpg',
        $fakeImage->getContent(),
    );

    $photo = Photo::factory()->create([
        'filename' => 'small.jpg',
        'original_path' => 'originals/small.jpg',
        'width' => null,
        'height' => null,
        'thumbnail_path' => null,
        'medium_path' => null,
        'large_path' => null,
        'processing_status' => ProcessingStatus::Pending,
    ]);

    $processor = app(InterventionImageProcessor::class);
    $processor->generate($photo);

    $photo->refresh();

    // Width/height should remain the original dimensions (no upscale)
    expect($photo->width)->toBe(150);
    expect($photo->height)->toBe(75);

    // All variant files should still be created
    Storage::disk('photos')->assertExists("thumbnail/{$photo->id}.jpg");
    Storage::disk('photos')->assertExists("medium/{$photo->id}.jpg");
    Storage::disk('photos')->assertExists("large/{$photo->id}.jpg");
});
