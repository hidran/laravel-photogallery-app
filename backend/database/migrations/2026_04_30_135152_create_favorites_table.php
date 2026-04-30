<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESIGN.md §4.6 — favorites pivot (users ↔ photos).
     *
     * Replaces the is_favorite boolean on photos with a many-to-many
     * relationship so any authenticated user can favorite any photo.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('photo_id')
                ->constrained('photos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'photo_id']);
            $table->index('photo_id');
        });

        // Data migration: move existing is_favorite=true to pivot rows
        if (Schema::hasColumn('photos', 'is_favorite')) {
            DB::table('photos')
                ->where('is_favorite', true)
                ->orderBy('id')
                ->each(function (object $photo) {
                    DB::table('favorites')->insertOrIgnore([
                        'user_id' => $photo->user_id,
                        'photo_id' => $photo->id,
                        'created_at' => now(),
                    ]);
                });

            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('photos_favorite_created_idx');
                $table->dropColumn('is_favorite');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('photos', 'is_favorite')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->boolean('is_favorite')->default(false)->after('mime_type');
                $table->index(['is_favorite', 'created_at'], 'photos_favorite_created_idx');
            });
        }

        Schema::dropIfExists('favorites');
    }
};
