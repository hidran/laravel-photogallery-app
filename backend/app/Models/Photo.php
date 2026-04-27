<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidV7;
use App\Enums\ProcessingStatus;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory, HasUuidV7;

    /**
     * Mass-assignable columns.
     *
     * IMPORTANT: every API request that fills() this model MUST go through
     * its FormRequest's validated() output (CLAUDE.md rule 6). The
     * `processing_*` columns are present here so the upload action and the
     * ProcessPhoto job can update() them, but no FormRequest may include
     * those keys — they're job-internal state, not user input.
     */
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
        // The pivot only carries created_at (DESIGN.md §4.5 — no updated_at);
        // withPivot exposes it without forcing Eloquent to write updated_at.
        return $this->belongsToMany(Tag::class, 'photo_tag')
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
