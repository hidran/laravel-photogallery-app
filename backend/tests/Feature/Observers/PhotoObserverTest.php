<?php

declare(strict_types=1);

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('removes the original from photos_private and the 3 variants from photos on photo delete', function () {
    Storage::fake('photos');
    Storage::fake('photos_private');

    $photo = Photo::factory()->processed()->create();

    Storage::disk('photos_private')->put($photo->original_path, 'fake-original-bytes');
    Storage::disk('photos')->put($photo->thumbnail_path, 'fake-thumb');
    Storage::disk('photos')->put($photo->medium_path, 'fake-medium');
    Storage::disk('photos')->put($photo->large_path, 'fake-large');

    Storage::disk('photos_private')->assertExists($photo->original_path);
    Storage::disk('photos')->assertExists([
        $photo->thumbnail_path,
        $photo->medium_path,
        $photo->large_path,
    ]);

    $photo->delete();

    Storage::disk('photos_private')->assertMissing($photo->original_path);
    Storage::disk('photos')->assertMissing([
        $photo->thumbnail_path,
        $photo->medium_path,
        $photo->large_path,
    ]);
});

it('does not blow up when variant paths are null (failed/processing photos)', function () {
    Storage::fake('photos');
    Storage::fake('photos_private');

    $photo = Photo::factory()->create([
        'thumbnail_path' => null,
        'medium_path' => null,
        'large_path' => null,
    ]);

    Storage::disk('photos_private')->put($photo->original_path, 'fake-original-bytes');

    expect(fn () => $photo->delete())->not->toThrow(Throwable::class);

    Storage::disk('photos_private')->assertMissing($photo->original_path);
});
