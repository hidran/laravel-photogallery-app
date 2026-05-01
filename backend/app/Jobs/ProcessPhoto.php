<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ExifExtractor;
use App\Contracts\ImageProcessor;
use App\Enums\ProcessingStatus;
use App\Events\PhotoProcessed;
use App\Models\Photo;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class ProcessPhoto implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly Photo $photo) {}

    public function handle(ImageProcessor $processor, ExifExtractor $exif): void
    {
        $this->photo->update(['processing_status' => ProcessingStatus::Processing]);

        $processor->generate($this->photo);

        $absolutePath = Storage::disk('photos_private')->path($this->photo->original_path);
        $exifData = $exif->extract($absolutePath);

        $this->photo->update([
            'exif' => $exifData,
            'processing_status' => ProcessingStatus::Completed,
        ]);

        event(new PhotoProcessed($this->photo));
    }

    public function failed(\Throwable $e): void
    {
        report($e);

        $this->photo->update([
            'processing_status' => ProcessingStatus::Failed,
            'processing_error' => 'Processing failed. Please try again or contact support.',
            'thumbnail_path' => null,
            'medium_path' => null,
            'large_path' => null,
        ]);
    }
}
