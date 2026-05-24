<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Services\Storage\DiskPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('photos');
    Storage::fake('photos_private');

    $this->storage = new DiskPhotoStorage;
});

it('stores an original file on the private disk', function (): void {
    $file = UploadedFile::fake()->image('sunset.jpg', 800, 600);
    $photoId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    $path = $this->storage->storeOriginal($file, $photoId);

    expect($path)->toBe("originals/{$photoId}.jpg");
    Storage::disk('photos_private')->assertExists($path);
});

it('stores a variant on the public disk', function (): void {
    $photoId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $contents = 'fake-image-bytes';

    $path = $this->storage->storeVariant($photoId, 'thumbnail', $contents);

    expect($path)->toBe("thumbnail/{$photoId}.jpg");
    Storage::disk('photos')->assertExists($path);
    expect(Storage::disk('photos')->get($path))->toBe($contents);
});

it('returns a public variant URL', function (): void {
    $relativePath = 'thumbnail/some-id.jpg';

    $url = $this->storage->publicVariantUrl($relativePath);

    expect($url)->toContain($relativePath);
});

it('returns a fallback URL for signedOriginalUrl on local disk', function (): void {
    $photo = new Photo;
    $photo->original_path = 'originals/test-id.jpg';

    Storage::disk('photos_private')->put($photo->original_path, 'original-bytes');

    $url = $this->storage->signedOriginalUrl($photo, 600);

    expect($url)->toContain($photo->original_path);
});

it('stores a variant from a stream resource', function (): void {
    $photoId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $contents = 'fake-image-bytes-from-stream';
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    $path = $this->storage->storeVariant($photoId, 'thumbnail', $stream);

    expect($path)->toBe("thumbnail/{$photoId}.jpg");
    Storage::disk('photos')->assertExists($path);
    expect(Storage::disk('photos')->get($path))->toBe($contents);
    fclose($stream);
});
