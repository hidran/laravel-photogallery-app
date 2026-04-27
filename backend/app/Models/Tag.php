<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

final class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory, HasUuidV7;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Auto-fill slug from name when missing. Slug collisions are resolved
     * with -2 / -3 / … suffixes by App\Services\TagAssigner (T028).
     */
    protected static function booted(): void
    {
        self::creating(function (self $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_tag')
            ->withTimestamps();
    }
}
