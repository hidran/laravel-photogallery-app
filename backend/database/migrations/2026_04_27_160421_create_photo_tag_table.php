<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESIGN.md §4.5 — photo_tag pivot.
     *
     * Composite (photo_id, tag_id) PK; both FKs CASCADE in both
     * directions so deleting a photo or tag wipes its row(s) without
     * orphans. created_at marks when the tag was attached (used by the
     * Filament admin's recent-tagging activity views).
     */
    public function up(): void
    {
        Schema::create('photo_tag', function (Blueprint $table) {
            $table->foreignUuid('photo_id')
                ->constrained('photos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignUuid('tag_id')
                ->constrained('tags')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['photo_id', 'tag_id'], 'photo_tag_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_tag');
    }
};
