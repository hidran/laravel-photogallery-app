<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolves the circular FK between albums.cover_photo_id and
     * photos.album_id. The cover_photo_id column was created in T008
     * without a constraint; now that photos exists (T010) we can add
     * the FK. ON DELETE SET NULL — deleting a photo just unsets it as
     * a cover; the album survives.
     */
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->foreign('cover_photo_id', 'albums_cover_photo_id_foreign')
                ->references('id')->on('photos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            // Pass the column array (not the index name) — SQLite's
            // grammar can only drop FKs via column-array (it rewrites the
            // table); MySQL/PostgreSQL handle either form.
            $table->dropForeign(['cover_photo_id']);
        });
    }
};
