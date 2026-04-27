<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESIGN.md §4.2 — albums.
     *
     * `cover_photo_id` is created here without its FK constraint; the FK is
     * added in `add_cover_photo_fk_to_albums` (T011) once the photos table
     * exists. This breaks the circular FK between albums ↔ photos.
     */
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('cover_photo_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'albums_user_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
