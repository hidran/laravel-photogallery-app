import { useState, useEffect, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Search, Upload, LogOut, ChevronDown, Camera } from 'lucide-react';
import { useDebounce } from '../hooks';
import { useMe, useLogout } from '../hooks/useAuth';
import { getToken } from '../api/client';
import { eventBus } from '../lib/eventBus';
import { copy } from '../data/copy';

type SortOption = 'newest' | 'oldest' | 'title_asc' | 'title_desc' | 'favorites_first';

const sortLabels: Record<SortOption, string> = {
  newest: copy.sort.newest,
  oldest: copy.sort.oldest,
  title_asc: copy.sort.titleAsc,
  title_desc: copy.sort.titleDesc,
  favorites_first: copy.sort.favoritesFirst,
};

export function Navbar() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '');
  const debouncedSearch = useDebounce(searchInput, 300);
  const { data: meData } = useMe();
  const logoutMutation = useLogout();
  const [sortOpen, setSortOpen] = useState(false);
  const sortRef = useRef<HTMLDivElement>(null);

  const currentSort = (() => {
    const s = searchParams.get('sort');
    const o = searchParams.get('order');
    if (!s) return 'newest' as SortOption;
    if (s === 'created_at' && o === 'asc') return 'oldest' as SortOption;
    if (s === 'title' && o === 'asc') return 'title_asc' as SortOption;
    if (s === 'title' && o === 'desc') return 'title_desc' as SortOption;
    if (s === 'favorites') return 'favorites_first' as SortOption;
    return 'newest' as SortOption;
  })();

  useEffect(() => {
    if (!sortOpen) return;
    function handleClickOutside(e: MouseEvent) {
      if (sortRef.current && !sortRef.current.contains(e.target as Node)) {
        setSortOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [sortOpen]);

  useEffect(() => {
    if (!sortOpen) return;
    function handleEscape(e: KeyboardEvent) {
      if (e.key === 'Escape') setSortOpen(false);
    }
    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [sortOpen]);

  useEffect(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (debouncedSearch) {
        next.set('search', debouncedSearch);
      } else {
        next.delete('search');
      }
      return next;
    });
  }, [debouncedSearch, setSearchParams]);

  function handleSortChange(sort: SortOption) {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (sort === 'newest') {
        next.delete('sort');
        next.delete('order');
      } else {
        const mapping: Record<SortOption, { sort: string; order: string }> = {
          newest: { sort: 'created_at', order: 'desc' },
          oldest: { sort: 'created_at', order: 'asc' },
          title_asc: { sort: 'title', order: 'asc' },
          title_desc: { sort: 'title', order: 'desc' },
          favorites_first: { sort: 'favorites', order: 'desc' },
        };
        const mapped = mapping[sort];
        next.set('sort', mapped.sort);
        next.set('order', mapped.order);
      }
      return next;
    });
    setSortOpen(false);
  }

  function handleUploadClick() {
    eventBus.emit('upload-modal:open', undefined);
  }

  function handleLogout() {
    logoutMutation.mutate();
  }

  const isAuthenticated = getToken() !== null;
  const userName = meData?.data?.name ?? '';

  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4">
      {/* Brand */}
      <div className="flex items-center gap-2">
        <Camera className="h-5 w-5 text-brand-600" />
        <h1 className="text-base font-bold tracking-tight text-gray-900">{copy.app.name}</h1>
      </div>

      {/* Search */}
      <div className="relative ml-auto flex max-w-sm flex-1 items-center">
        <Search className="pointer-events-none absolute left-3 h-4 w-4 text-gray-400" />
        <input
          type="search"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={copy.search.placeholder}
          className="w-full rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-9 pr-3 text-sm placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500"
        />
      </div>

      {/* Sort */}
      <div className="relative" ref={sortRef}>
        <button
          type="button"
          onClick={() => setSortOpen(!sortOpen)}
          className="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
        >
          {sortLabels[currentSort]}
          <ChevronDown className="h-3.5 w-3.5" />
        </button>
        {sortOpen && (
          <div className="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
            {(Object.keys(sortLabels) as SortOption[]).map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => handleSortChange(option)}
                className={`block w-full px-3 py-1.5 text-left text-sm ${
                  currentSort === option
                    ? 'bg-brand-50 font-medium text-brand-700'
                    : 'text-gray-600 hover:bg-gray-50'
                }`}
              >
                {sortLabels[option]}
              </button>
            ))}
          </div>
        )}
      </div>

      {isAuthenticated ? (
        <>
          <button
            type="button"
            onClick={handleUploadClick}
            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
          >
            <Upload className="h-4 w-4" />
            {copy.upload.title}
          </button>

          <div className="flex items-center gap-1.5 border-l border-gray-200 pl-3">
            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">
              {userName.charAt(0).toUpperCase()}
            </div>
            <span className="text-sm font-medium text-gray-700">{userName}</span>
            <button
              type="button"
              onClick={handleLogout}
              className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label={copy.auth.logout}
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </>
      ) : (
        <a
          href="/login"
          className="inline-flex items-center rounded-lg bg-brand-600 px-3.5 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
        >
          {copy.auth.login}
        </a>
      )}
    </header>
  );
}
