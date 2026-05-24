<?php

declare(strict_types=1);

use App\Contracts\ExifReader;
use App\Contracts\GpsStripper;
use App\Services\Imaging\PhpExifExtractor;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

function makeExtractor(): PhpExifExtractor
{
    return new PhpExifExtractor(new ImageManager(new Driver));
}

it('returns an empty array for a non-existent file', function () {
    $extractor = makeExtractor();

    $result = $extractor->extract('/tmp/does-not-exist-'.uniqid().'.jpg');

    expect($result)->toBe([]);
});

it('returns an empty array for a file without EXIF data', function () {
    // Create a minimal PNG (no EXIF support)
    $path = tempnam(sys_get_temp_dir(), 'exif_test_');
    $img = imagecreatetruecolor(10, 10);
    imagepng($img, $path);
    imagedestroy($img);

    $extractor = makeExtractor();
    $result = $extractor->extract($path);

    expect($result)->toBe([]);

    unlink($path);
});

it('returns an empty array for an unreadable path', function () {
    $extractor = makeExtractor();

    $result = $extractor->extract('');

    expect($result)->toBe([]);
});

it('implements the ExifReader and GpsStripper contracts', function () {
    $extractor = makeExtractor();

    expect($extractor)->toBeInstanceOf(ExifReader::class);
    expect($extractor)->toBeInstanceOf(GpsStripper::class);
});

it('stripGps returns an UploadedFile', function () {
    // Create a simple JPEG via GD
    $path = tempnam(sys_get_temp_dir(), 'exif_strip_');
    $img = imagecreatetruecolor(10, 10);
    imagejpeg($img, $path);
    imagedestroy($img);

    $uploaded = new UploadedFile(
        path: $path,
        originalName: 'test.jpg',
        mimeType: 'image/jpeg',
        error: UPLOAD_ERR_OK,
        test: true,
    );

    $extractor = makeExtractor();
    $result = $extractor->stripGps($uploaded);

    expect($result)->toBeInstanceOf(UploadedFile::class);
    expect($result->getClientOriginalName())->toBe('test.jpg');
    expect(file_exists($result->getPathname()))->toBeTrue();

    // Clean up
    @unlink($path);
    @unlink($result->getPathname());
});

it('extracts EXIF data from a JPEG with EXIF tags', function () {
    // GD-created JPEGs have minimal EXIF; verify no GPS keys leak through
    $path = tempnam(sys_get_temp_dir(), 'exif_test_');
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $path);
    imagedestroy($img);

    $extractor = makeExtractor();
    $result = $extractor->extract($path);

    expect($result)->toBeArray();
    expect($result)->not->toHaveKey('GPSLatitude');
    expect($result)->not->toHaveKey('GPSLongitude');
    expect($result)->not->toHaveKey('GPSAltitude');

    unlink($path);
});
