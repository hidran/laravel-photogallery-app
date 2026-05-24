<?php

declare(strict_types=1);

namespace App\Services\Imaging;

use App\Contracts\ImageProcessor;
use App\Contracts\PhotoStorage;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

final class InterventionImageProcessor implements ImageProcessor
{
    /** @var array<string, string> Maps variant key → Photo column name */
    private const array VARIANT_COLUMNS = [
        'thumbnail' => 'thumbnail_path',
        'medium' => 'medium_path',
        'large' => 'large_path',
    ];

    public function __construct(
        private readonly PhotoStorage $storage,
    ) {}

    public function generate(Photo $photo): void
    {
        // Stream the original to a temp file instead of loading the entire
        // image into a PHP string. This prevents OOM on high-resolution
        // photos (20+ MP) that exceed PHP's memory limit.
        $stream = Storage::disk('photos_private')->readStream($photo->original_path);
        if ($stream === null) {
            throw new RuntimeException("Cannot read original file: {$photo->original_path}");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'photo_process_');
        if ($tempPath === false) {
            throw new RuntimeException('Failed to create temp file for image processing');
        }

        try {
            file_put_contents($tempPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $original = Image::decodePath($tempPath);
            $original->orient();

            $maxDim = (int) config('photogallery.images.max_dimension', 8000);
            if ($original->width() > $maxDim || $original->height() > $maxDim) {
                throw new RuntimeException(
                    "Image dimensions ({$original->width()}x{$original->height()}) exceed maximum allowed ({$maxDim}x{$maxDim})."
                );
            }

            $photo->width = $original->width();
            $photo->height = $original->height();

            /** @var array<string, array{width: int, quality: int}> $variants */
            $variants = config('photogallery.images.variants');

            foreach ($variants as $key => $spec) {
                $variant = clone $original;
                $variant->scaleDown(width: $spec['width']);

                $encoded = $variant->encode(new JpegEncoder(quality: $spec['quality'], strip: true));

                $path = $this->storage->storeVariant($photo->id, $key, $encoded->toStream());

                $column = self::VARIANT_COLUMNS[$key];
                $photo->{$column} = $path;
            }

            $photo->save();
        } finally {
            @unlink($tempPath);
        }
    }
}
