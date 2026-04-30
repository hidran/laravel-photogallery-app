<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

final class BatchController extends Controller
{
    public function show(Request $request, string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Batch not found.'], 404);
        }

        $photos = Photo::query()
            ->where('created_at', '>=', $batch->createdAt)
            ->whereIn('processing_status', ['pending', 'processing', 'completed', 'failed'])
            ->get()
            ->filter(fn (Photo $photo) => $photo->user_id === $request->user()?->id)
            ->values();

        $pending = $photos->where('processing_status', 'pending')->count()
            + $photos->where('processing_status', 'processing')->count();
        $failed = $photos->where('processing_status', 'failed')->count();

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'total' => $batch->totalJobs,
                'pending' => $batch->pendingJobs,
                'processed' => $batch->totalJobs - $batch->pendingJobs - $batch->failedJobs,
                'failed' => $batch->failedJobs,
                'finished' => $batch->finished(),
                'cancelled' => $batch->cancelled(),
                'created_at' => $batch->createdAt->toIso8601String(),
                'finished_at' => $batch->finishedAt?->toIso8601String(),
                'photos' => $photos->map(fn (Photo $photo) => [
                    'id' => $photo->id,
                    'processing_status' => $photo->processing_status->value,
                    'urls' => [
                        'thumbnail' => $photo->thumbnail_path,
                        'medium' => $photo->medium_path,
                        'large' => $photo->large_path,
                    ],
                ])->all(),
            ],
        ]);
    }
}
