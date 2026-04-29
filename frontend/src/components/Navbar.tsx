import { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Search, Upload, LogOut, ChevronDown } from 'lucide-react';
import { useDebounce } from '../hooks';
import { useMe, useLogout } from '../hooks/useAuth';
import { getToken } from '../api/client';
import { copy } from '../data/copy';

type SortOption = 'newest' | 'oldest' | 'title_asc' | 'title_desc';

const sortLabels: Record<SortOption, string> = {
  newest: 'Newest',
  oldest: 'Oldest',
  title_asc: 'Title A–Z',
  title_desc: 'Title Z–A',
};

export function Navbar() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '');
  const debouncedSearch = useDebounce(searchInput, 300);
  const { data: meData } = useMe();
  const logoutMutation = useLogout();
  const [sortOpen, setSortOpen] = useState(false);

  const currentSort = (searchParams.get('sort') as SortOption) ?? 'newest';

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
      } else {
        next.set('sort', sort);
      }
      return next;
    });
    setSortOpen(false);
  }

  function handleUploadClick() {
    window.dispatchEvent(new CustomEvent('open-upload-modal'));
  }

  function handleLogout() {
    logoutMutation.mutate();
  }

  const isAuthenticated = getToken() !== null;
  const userName = meData?.data.user.name ?? '';

  return (
    <header className="flex h-16 shrink-0 items-center gap-4 border-b border-gray-200 bg-white px-4 shadow-sm">
      <h1 className="text-lg font-semibold text-gray-900">{copy.app.name}</h1>

      {/* Search */}
      <div className="relative ml-auto flex max-w-md flex-1 items-center">
        <Search className="pointer-events-none absolute left-3 h-4 w-4 text-gray-400" />
        <input
          type="search"
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={copy.search.placeholder}
          className="w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 text-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>

      {/* Sort Dropdown */}
      <div className="relative">
        <button
          type="button"
          onClick={() => setSortOpen(!sortOpen)}
          className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          {sortLabels[currentSort]}
          <ChevronDown className="h-4 w-4" />
        </button>
        {sortOpen && (
          <div className="absolute right-0 z-10 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
            {(Object.keys(sortLabels) as SortOption[]).map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => handleSortChange(option)}
                className={`block w-full px-4 py-2 text-left text-sm ${
                  currentSort === option
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-gray-700 hover:bg-gray-100'
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
          {/* Upload Button — auth only */}
          <button
            type="button"
            onClick={handleUploadClick}
            className="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          >
            <Upload className="h-4 w-4" />
            {copy.upload.title}
          </button>

          {/* Auth Menu */}
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-700">{userName}</span>
            <button
              type="button"
              onClick={handleLogout}
              className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900"
            >
              <LogOut className="h-4 w-4" />
              {copy.auth.logout}
            </button>
          </div>
        </>
      ) : (
        <a
          href="/login"
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          {copy.auth.login}
        </a>
      )}
    </header>
  );
}
