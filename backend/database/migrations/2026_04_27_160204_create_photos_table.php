<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESIGN.md §4.4 — photos.
     *
     * Indexes are tuned to the query patterns the gallery actually uses
     * (newest album view, favorites filter, admin queue dashboard).
     * FULLTEXT(title, description) is MySQL/PostgreSQL only; on SQLite
     * (used for local dev + in-memory tests) the search path falls back
     * to LIKE in PhotoQuery.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('original_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('medium_path', 500)->nullable();
            $table->string('large_path', 500)->nullable();
            $table->foreignUuid('album_id')
                ->nullable()
                ->constrained('albums')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->boolean('is_favorite')->default(false);
            $table->json('exif')->nullable();
            $table->string('processing_status', 20)->default('pending');
            $table->unsignedTinyInteger('processing_attempts')->default(0);
            $table->text('processing_error')->nullable();
            $table->timestamps();

            // Per DESIGN.md §4 conventions: every FK column has an index.
            // foreignUuid()->constrained() does NOT auto-add an index on
            // SQLite/PostgreSQL, so we add it explicitly here for user_id.
            // (album_id is already covered by the composite below.)
            $table->index('user_id', 'photos_user_idx');
            // Composite indexes from §4.4 — each one covers a real query path.
            $table->index(['album_id', 'created_at'], 'photos_album_created_idx');
            $table->index(['is_favorite', 'created_at'], 'photos_favorite_created_idx');
            $table->index(['processing_status', 'created_at'], 'photos_status_created_idx');
            $table->index('created_at', 'photos_created_idx');
        });

        // FULLTEXT only on engines that support it (MySQL, PostgreSQL).
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            Schema::table('photos', function (Blueprint $table) {
                $table->fullText(['title', 'description'], 'photos_search_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
