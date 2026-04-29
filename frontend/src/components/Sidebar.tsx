import { NavLink, useSearchParams } from 'react-router-dom';
import { Images, Heart } from 'lucide-react';
import { useAlbums } from '../hooks/useAlbums';
import { useTags } from '../hooks/useTags';
import { copy } from '../data/copy';

export function Sidebar() {
  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();
  const [searchParams, setSearchParams] = useSearchParams();

  const albums = albumsData?.data ?? [];
  const tags = tagsData?.data ?? [];

  const activeTags = searchParams.getAll('tags[]');

  function toggleTag(slug: string) {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      const current = next.getAll('tags[]');
      next.delete('tags[]');

      if (current.includes(slug)) {
        current.filter((t) => t !== slug).forEach((t) => next.append('tags[]', t));
      } else {
        [...current, slug].forEach((t) => next.append('tags[]', t));
      }

      return next;
    });
  }

  return (
    <aside className="flex w-64 shrink-0 flex-col gap-6 overflow-y-auto border-r border-gray-200 bg-gray-50 p-4">
      {/* Navigation Links */}
      <nav className="flex flex-col gap-1">
        <NavLink
          to="/"
          end
          className={({ isActive }) =>
            `inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium ${
              isActive ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-200'
            }`
          }
        >
          <Images className="h-4 w-4" />
          {copy.nav.allPhotos}
        </NavLink>

        <NavLink
          to="/favorites"
          className={({ isActive }) =>
            `inline-flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium ${
              isActive ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-200'
            }`
          }
        >
          <span className="inline-flex items-center gap-2">
            <Heart className="h-4 w-4" />
            {copy.nav.favorites}
          </span>
        </NavLink>
      </nav>

      {/* Albums Section */}
      <section>
        <h2 className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">
          {copy.nav.albums}
        </h2>
        <nav className="flex flex-col gap-1">
          {albums.map((album) => (
            <NavLink
              key={album.id}
              to={`/albums/${album.id}`}
              className={({ isActive }) =>
                `inline-flex items-center justify-between rounded-md px-3 py-2 text-sm ${
                  isActive ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-200'
                }`
              }
            >
              <span className="truncate">{album.name}</span>
              <span className="ml-2 text-xs text-gray-400">{album.photos_count}</span>
            </NavLink>
          ))}
          {albums.length === 0 && (
            <p className="px-3 text-xs text-gray-400">{copy.albums.emptyState}</p>
          )}
        </nav>
      </section>

      {/* Tags Section */}
      <section>
        <h2 className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">
          {copy.nav.tags}
        </h2>
        <div className="flex flex-wrap gap-2 px-3">
          {tags.map((tag) => (
            <button
              key={tag.id}
              type="button"
              onClick={() => toggleTag(tag.slug)}
              aria-pressed={activeTags.includes(tag.slug)}
              className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${
                activeTags.includes(tag.slug)
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              }`}
            >
              {tag.name}
            </button>
          ))}
        </div>
      </section>
    </aside>
  );
}
