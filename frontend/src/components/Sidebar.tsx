import { NavLink } from 'react-router-dom';
import { Images, Heart } from 'lucide-react';
import { AlbumList } from './sidebar/AlbumList';
import { TagCloud } from './sidebar/TagCloud';
import { StatsBar } from './sidebar/StatsBar';
import { useAlbums } from '../hooks/useAlbums';
import { useTags } from '../hooks/useTags';
import { getToken } from '../api/client';
import { copy } from '../data/copy';

export function Sidebar() {
  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();

  const albums = albumsData?.data ?? [];
  const tags = tagsData?.data ?? [];
  const isAuthenticated = getToken() !== null;

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
      isActive
        ? 'bg-sidebar-active text-sidebar-text-bright'
        : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-bright'
    }`;

  return (
    <aside className="flex w-64 shrink-0 flex-col bg-sidebar">
      {/* Navigation */}
      <nav className="flex flex-col gap-0.5 p-3">
        <NavLink to="/" end className={navLinkClass}>
          <Images className="h-4 w-4" />
          {copy.nav.allPhotos}
        </NavLink>
        <NavLink to="/favorites" className={navLinkClass}>
          <Heart className="h-4 w-4" />
          {copy.nav.favorites}
        </NavLink>
      </nav>

      <div className="mx-3 border-t border-white/10" />

      {/* Albums + Tags */}
      <section className="flex-1 overflow-y-auto p-3">
        <AlbumList isAuthenticated={isAuthenticated} />

        <div className="mx-0 my-3 border-t border-white/10" />

        <TagCloud isAuthenticated={isAuthenticated} />
      </section>

      {/* Stats bar */}
      <StatsBar albums={albums} tagCount={tags.length} />
    </aside>
  );
}
