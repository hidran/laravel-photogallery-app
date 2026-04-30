<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Middleware\ETag;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| /api/v1
|--------------------------------------------------------------------------
|
| Mounted with apiPrefix=api/v1 in bootstrap/app.php so route declarations
| here omit the prefix. Rate limiters are defined in AppServiceProvider:
|
|   throttle:auth — 10/min/IP   (DESIGN.md §6.0)
|   throttle:api  — 120/min/IP AND 300/min/user
|
| Mutating endpoints carry both `auth:sanctum` (token authentication) and
| an in-controller tokenCan() check for the right ability per §10.3.
*/

// Health is intentionally unthrottled so external uptime probes don't
// trip the rate limiter (DESIGN.md §6.5).
Route::get('/health', [HealthController::class, 'index']);

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
});

Route::middleware('throttle:api')->group(function () {
    // Public reads.
    Route::get('/photos', [PhotoController::class, 'index']);
    Route::get('/photos/{photo}', [PhotoController::class, 'show'])->middleware(ETag::class);

    Route::get('/albums', [AlbumController::class, 'index']);
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->middleware(ETag::class);

    Route::get('/tags', [TagController::class, 'index']);

    // Mutating routes — Sanctum token + in-controller tokenCan gate.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/photos', [PhotoController::class, 'store']);
        Route::patch('/photos/{photo}', [PhotoController::class, 'update']);
        Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
        Route::put('/photos/{photo}/favorite', [PhotoController::class, 'favorite']);
        Route::delete('/photos/{photo}/favorite', [PhotoController::class, 'unfavorite']);
        Route::delete('/photos/favorites/batch', [PhotoController::class, 'unfavoriteBatch']);

        Route::post('/albums', [AlbumController::class, 'store']);
        Route::patch('/albums/{album}', [AlbumController::class, 'update']);
        Route::delete('/albums/{album}', [AlbumController::class, 'destroy']);
    });

    // Batch progress — auth required (must be batch creator).
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/photos/batch/{batchId}', [BatchController::class, 'show']);
    });
});
