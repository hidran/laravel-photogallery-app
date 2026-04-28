<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * DESIGN.md §6.5 — GET /health.
 *
 * Probes the two failure modes that actually break the app: the photos
 * disk (write+read+delete a marker file) and the queue (peek the
 * failed_jobs table — fails if the connection is broken). Returns 200
 * when both pass, 503 with a `checks` map when any fail.
 */
final class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'storage' => $this->probeStorage(),
            'queue' => $this->probeQueue(),
        ];

        $healthy = ! in_array('error', $checks, true);

        return response()->json(
            array_merge(['status' => $healthy ? 'ok' : 'unavailable'], $checks),
            $healthy ? 200 : 503
        );
    }

    private function probeStorage(): string
    {
        try {
            $disk = Storage::disk('photos');
            $marker = '_health/'.Str::uuid7().'.txt';
            $disk->put($marker, 'ok');
            $contents = $disk->get($marker);
            $disk->delete($marker);

            return $contents === 'ok' ? 'ok' : 'error';
        } catch (Throwable) {
            return 'error';
        }
    }

    private function probeQueue(): string
    {
        try {
            // We don't care about the result — just that the connection
            // resolves. failed_jobs is the canonical queue-side table to
            // probe; in production with SQS, this still hits the local
            // RDBMS, which is what the worker uses for checkpointing.
            DB::table('failed_jobs')->count();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }
}
