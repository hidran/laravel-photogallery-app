<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DESIGN.md §4.3 — tags.
     *
     * Tags are GLOBAL: no user_id. Two members tagging "sunset" reference
     * the same row. Slug is auto-generated in the model's booted() hook.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique('tags_name_unique');
            $table->string('slug', 120)->unique('tags_slug_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
