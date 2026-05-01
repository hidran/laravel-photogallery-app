<?php

declare(strict_types=1);

namespace App\Services\Imaging;

use App\Contracts\ExifExtractor;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Laravel\Facades\Image;

final class PhpExifExtractor implements ExifExtractor
{
    /**
     * Extract sanitized EXIF data from a local file path.
     * Never includes GPS data. Returns [] on failure or missing EXIF.
     *
     * @return array<string, mixed>
     */
    public function extract(string $absolutePath): array
    {
        if (! file_exists($absolutePath) || ! is_readable($absolutePath)) {
            return [];
        }

        try {
            $exif = @exif_read_data($absolutePath, 'ANY_TAG', true);
        } catch (\Throwable) {
            return [];
        }

        if ($exif === false) {
            return [];
        }

        // Flatten sections into a single array for easier access
        $flat = [];
        foreach ($exif as $section => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $flat[$key] = $value;
                }
            }
        }

        // Also merge the top-level COMPUTED section if present
        if (isset($exif['COMPUTED']) && is_array($exif['COMPUTED'])) {
            foreach ($exif['COMPUTED'] as $key => $value) {
                $flat['COMPUTED.'.$key] = $value;
            }
        }

        $result = [];

        // camera → Model or "Make Model"
        $camera = $this->extractCamera($flat);
        if ($camera !== null) {
            $result['camera'] = $camera;
        }

        // iso → ISOSpeedRatings
        if (isset($flat['ISOSpeedRatings'])) {
            $result['iso'] = (int) $flat['ISOSpeedRatings'];
        }

        // aperture → COMPUTED.ApertureFNumber or format from FNumber
        $aperture = $this->extractAperture($flat);
        if ($aperture !== null) {
            $result['aperture'] = $aperture;
        }

        // shutter → ExposureTime
        if (isset($flat['ExposureTime']) && is_string($flat['ExposureTime'])) {
            $result['shutter'] = $flat['ExposureTime'];
        }

        // focal_length → FocalLength
        $focalLength = $this->extractFocalLength($flat);
        if ($focalLength !== null) {
            $result['focal_length'] = $focalLength;
        }

        // taken_at → DateTimeOriginal
        if (isset($flat['DateTimeOriginal']) && is_string($flat['DateTimeOriginal'])) {
            $result['taken_at'] = $flat['DateTimeOriginal'];
        }

        return $result;
    }

    /**
     * Returns a copy of $file with all GPS EXIF tags removed.
     */
    public function stripGps(UploadedFile $file): UploadedFile
    {
        $image = Image::decodePath($file->getPathname());

        // Re-encode the image which strips all EXIF (including GPS)
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        $extension = in_array($ext, $allowed, true) ? $ext : 'jpg';
        $encoded = $image->encodeUsingFileExtension($extension);

        $tempPath = tempnam(sys_get_temp_dir(), 'exif_strip_');
        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temp file for EXIF stripping');
        }

        try {
            file_put_contents($tempPath, (string) $encoded);

            return new UploadedFile(
                path: $tempPath,
                originalName: $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                error: UPLOAD_ERR_OK,
                test: true,
            );
        } catch (\Throwable $e) {
            @unlink($tempPath);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    private function extractCamera(array $flat): ?string
    {
        $make = isset($flat['Make']) && is_string($flat['Make']) ? trim($flat['Make']) : null;
        $model = isset($flat['Model']) && is_string($flat['Model']) ? trim($flat['Model']) : null;

        if ($model === null) {
            return null;
        }

        // If model already contains the make, just return model
        if ($make !== null && ! str_contains($model, $make)) {
            return $make.' '.$model;
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    private function extractAperture(array $flat): ?string
    {
        // Prefer COMPUTED.ApertureFNumber
        if (isset($flat['COMPUTED.ApertureFNumber']) && is_string($flat['COMPUTED.ApertureFNumber'])) {
            return $flat['COMPUTED.ApertureFNumber'];
        }

        // Fall back to formatting FNumber (e.g. "28/10" → "f/2.8")
        if (isset($flat['FNumber']) && is_string($flat['FNumber'])) {
            $parts = explode('/', $flat['FNumber']);
            if (count($parts) === 2 && (int) $parts[1] !== 0) {
                $value = (int) $parts[0] / (int) $parts[1];

                return 'f/'.number_format($value, 1);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    private function extractFocalLength(array $flat): ?string
    {
        if (! isset($flat['FocalLength']) || ! is_string($flat['FocalLength'])) {
            return null;
        }

        $parts = explode('/', $flat['FocalLength']);
        if (count($parts) === 2 && (int) $parts[1] !== 0) {
            $value = (int) $parts[0] / (int) $parts[1];

            return number_format($value, 0).'mm';
        }

        return $flat['FocalLength'];
    }
}
