<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/health', [HealthController::class, 'index']);
    });
});

it('returns 200 with status=ok when storage and queue both pass', function () {
    Storage::fake('photos');

    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertJsonMissing(['storage', 'queue']);
});

it('returns 503 with status=unavailable when queue table is missing', function () {
    Storage::fake('photos');
    Schema::dropIfExists('failed_jobs');

    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(503)
        ->assertExactJson(['status' => 'unavailable']);
});
