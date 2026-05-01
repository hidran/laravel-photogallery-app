<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Photo;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Composable query builder for GET /photos and the Filament admin index
 * page (DESIGN.md §6.2). Each `with*` method returns a fresh PhotoQuery
 * so calls can be chained or skipped per the request's filter state.
 *
 * The `withSearch` path picks FULLTEXT on engines that support it
 * (MySQL/PostgreSQL — see the photos migration's driver-conditional
 * fulltext index) and falls back to LIKE on SQLite.
 */
final class PhotoQuery
{
    public function __construct(private Builder $query) {}

    public static function for(?Builder $base = null): self
    {
        return new self($base ?? Photo::query());
    }

    public function withSearch(?string $term): self
    {
        if ($term === null || $term === '') {
            return $this;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->query->whereRaw(
                'MATCH(title, description) AGAINST (? IN BOOLEAN MODE)',
                [$term]
            );
        } else {
            $needle = '%'.str_replace('%', '\%', $term).'%';
            $this->query->where(function (Builder $q) use ($needle): void {
                $q->where('title', 'like', $needle)
                    ->orWhere('description', 'like', $needle);
            });
        }

        return $this;
    }

    /**
     * @param  list<string>|null  $slugs
     */
    public function withTags(?array $slugs): self
    {
        if ($slugs === null || $slugs === []) {
            return $this;
        }

        // AND logic: each tag must be attached. Uses a subquery with
        // JOIN + HAVING instead of whereHas correlated subquery for
        // better scalability on large datasets.
        $this->query->whereIn('photos.id', function ($sub) use ($slugs): void {
            $sub->select('photo_tag.photo_id')
                ->from('photo_tag')
                ->join('tags', 'tags.id', '=', 'photo_tag.tag_id')
                ->whereIn('tags.slug', $slugs)
                ->groupBy('photo_tag.photo_id')
                ->havingRaw('COUNT(DISTINCT photo_tag.tag_id) = ?', [count($slugs)]);
        });

        return $this;
    }

    public function withAlbum(?string $albumId): self
    {
        if ($albumId === null) {
            return $this;
        }

        $this->query->where('album_id', $albumId);

        return $this;
    }

    public function withFavorites(bool $favoritesOnly, ?string $userId = null): self
    {
        if ($favoritesOnly && $userId !== null) {
            $this->query->whereHas('favoritedBy', fn (Builder $q) => $q->where('users.id', $userId));
        }

        return $this;
    }

    /**
     * Constrain the result set to the calling user's photos. The public
     * GET /photos endpoint is open (DESIGN.md §6.2), but the Filament
     * admin index and the SPA's `/favorites` route both filter by the
     * authenticated user — having this here keeps the controller layer
     * thin.
     */
    public function withOwner(string $userId): self
    {
        $this->query->where('user_id', $userId);

        return $this;
    }

    public function applySort(?string $sort, ?string $order): self
    {
        $sort = in_array($sort, ['created_at', 'title', 'favorites'], true)
            ? $sort
            : 'created_at';
        $order = $order === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'favorites' => $this->query->withCount('favoritedBy')
                ->orderBy('favorited_by_count', $order)
                ->orderBy('created_at', $order),
            'title' => $this->query->orderBy('title', $order),
            default => $this->query->orderBy('created_at', $order),
        };

        // Stable secondary sort on UUID v7 id — same logical instant,
        // deterministic ordering for cursor pagination.
        $this->query->orderBy('id', $order);

        return $this;
    }

    public function paginate(?string $cursor, int $perPage = 24): CursorPaginator
    {
        return $this->query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    public function builder(): Builder
    {
        return $this->query;
    }
}
