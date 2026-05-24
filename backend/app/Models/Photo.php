<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidV7;
use App\Enums\ProcessingStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'album_id',
    'title',
    'description',
    'filename',
    'original_path',
    'thumbnail_path',
    'medium_path',
    'large_path',
    'width',
    'height',
    'file_size',
    'mime_type',
    'exif',
    'batch_id',
    'processing_status',
    'processing_attempts',
    'processing_error',
])]
final class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, HasUuidV7;

    protected function casts(): array
    {
        return [
            'exif' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'processing_attempts' => 'integer',
            'processing_status' => ProcessingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        // The pivot only carries created_at (DESIGN.md §4.5 — no updated_at);
        // withPivot exposes it without forcing Eloquent to write updated_at.
        return $this->belongsToMany(Tag::class, 'photo_tag')
            ->withPivot('created_at');
    }

    /**
     * Users who have favorited this photo (DESIGN.md §4.6).
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withPivot('created_at');
    }

    /**
     * Inverse of Album::coverPhoto — the Album for which this Photo is the cover, if any.
     *
     * @return HasOne<Album, $this>
     */
    public function coverOf(): HasOne
    {
        return $this->hasOne(Album::class, 'cover_photo_id');
    }
}
