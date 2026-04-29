<?php

declare(strict_types=1);

namespace App\Services\Imaging;

use App\Contracts\ImageProcessor;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

final class InterventionImageProcessor implements ImageProcessor
{
    /** @var array<string, string> Maps variant key → Photo column name */
    private const array VARIANT_COLUMNS = [
        'thumbnail' => 'thumbnail_path',
        'medium' => 'medium_path',
        'large' => 'large_path',
    ];

    /** @var array<string, string> Maps variant key → output directory */
    private const array VARIANT_DIRS = [
        'thumbnail' => 'thumbnails',
        'medium' => 'medium',
        'large' => 'large',
    ];

    public function generate(Photo $photo): void
    {
        $privateDisk = Storage::disk('photos_private');
        $publicDisk = Storage::disk('photos');

        $contents = $privateDisk->get($photo->original_path);

        $original = Image::decode($contents);
        $original->orient();

        $photo->width = $original->width();
        $photo->height = $original->height();

        /** @var array<string, array{width: int, quality: int}> $variants */
        $variants = config('photogallery.images.variants');

        $filename = pathinfo($photo->filename, PATHINFO_FILENAME).'.jpg';

        foreach ($variants as $key => $spec) {
            $variant = clone $original;
            $variant->scaleDown(width: $spec['width']);

            $encoded = $variant->encode(new JpegEncoder(quality: $spec['quality'], strip: true));

            $dir = self::VARIANT_DIRS[$key];
            $path = "{$dir}/{$filename}";

            $publicDisk->put($path, $encoded->toString());

            $column = self::VARIANT_COLUMNS[$key];
            $photo->{$column} = $path;
        }

        $photo->save();
    }
}
