<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidV7;
use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoFactory> */
    use HasFactory, HasUuidV7;

    protected $fillable = [
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
        'is_favorite',
        'exif',
        'processing_status',
        'processing_attempts',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
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
        return $this->belongsToMany(Tag::class, 'photo_tag')
            ->withTimestamps();
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
