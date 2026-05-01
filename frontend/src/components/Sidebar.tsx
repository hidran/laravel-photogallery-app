import { useCallback, useState } from 'react';
import { NavLink, useSearchParams } from 'react-router-dom';
import { ConfirmDialog } from './ConfirmDialog';
import {
  Images,
  Heart,
  Plus,
  Pencil,
  Trash2,
  X,
  Check,
  Camera,
  FolderOpen,
  Tag,
} from 'lucide-react';
import { toast } from 'sonner';
import { useAlbums, useCreateAlbum, useUpdateAlbum, useDeleteAlbum } from '../hooks/useAlbums';
import { useTags } from '../hooks/useTags';
import { getToken } from '../api/client';
import { copy } from '../data/copy';

export function Sidebar() {
  const { data: albumsData } = useAlbums();
  const { data: tagsData } = useTags();
  const [searchParams, setSearchParams] = useSearchParams();
  const createAlbum = useCreateAlbum();
  const updateAlbum = useUpdateAlbum();
  const deleteAlbum = useDeleteAlbum();

  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newAlbumName, setNewAlbumName] = useState('');
  const [newAlbumDesc, setNewAlbumDesc] = useState('');
  const [editingAlbumId, setEditingAlbumId] = useState<string | null>(null);
  const [editingAlbumName, setEditingAlbumName] = useState('');
  const [newTagName, setNewTagName] = useState('');
  const [showTagInput, setShowTagInput] = useState(false);
  const [albumToDelete, setAlbumToDelete] = useState<string | null>(null);

  const albums = albumsData?.data ?? [];
  const tags = tagsData?.data ?? [];
  const isAuthenticated = getToken() !== null;
  const totalPhotos = albums.reduce((sum, album) => sum + album.photos_count, 0);

  const activeTags = searchParams.getAll('tags[]');

  const toggleTag = useCallback(
    (slug: string) => {
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
    },
    [setSearchParams],
  );

  const handleCreateAlbum = useCallback(() => {
    if (!newAlbumName.trim()) return;
    const payload: { name: string; description?: string } = { name: newAlbumName.trim() };
    if (newAlbumDesc.trim()) payload.description = newAlbumDesc.trim();
    createAlbum.mutate(payload, {
      onSuccess: () => {
        setNewAlbumName('');
        setNewAlbumDesc('');
        setShowCreateForm(false);
      },
      onError: () => toast.error(copy.errors.validationFailed),
    });
  }, [createAlbum, newAlbumName, newAlbumDesc]);

  const handleStartEdit = useCallback((albumId: string, currentName: string) => {
    setEditingAlbumId(albumId);
    setEditingAlbumName(currentName);
  }, []);

  const handleSaveEdit = useCallback(() => {
    if (!editingAlbumId || !editingAlbumName.trim()) return;
    updateAlbum.mutate(
      { id: editingAlbumId, payload: { name: editingAlbumName.trim() } },
      {
        onSuccess: () => setEditingAlbumId(null),
        onError: () => toast.error(copy.errors.validationFailed),
      },
    );
  }, [updateAlbum, editingAlbumId, editingAlbumName]);

  const handleDeleteAlbum = useCallback((albumId: string) => {
    setAlbumToDelete(albumId);
  }, []);

  const handleConfirmDeleteAlbum = useCallback(() => {
    if (!albumToDelete) return;
    deleteAlbum.mutate(albumToDelete, {
      onSuccess: () => setAlbumToDelete(null),
    });
  }, [deleteAlbum, albumToDelete]);

  const handleCreateTag = useCallback(() => {
    if (!newTagName.trim()) return;
    const slug = newTagName
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');
    if (slug) {
      toggleTag(slug);
      setNewTagName('');
      setShowTagInput(false);
    }
  }, [newTagName, toggleTag]);

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

      {/* Albums */}
      <section className="flex-1 overflow-y-auto p-3">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-[11px] font-semibold uppercase tracking-widest text-sidebar-text/60">
            {copy.nav.albums}
          </h2>
          {isAuthenticated && (
            <button
              type="button"
              onClick={() => setShowCreateForm((prev) => !prev)}
              className="rounded p-0.5 text-sidebar-text/50 transition-colors hover:text-sidebar-text-bright"
              aria-label={copy.albums.create}
            >
              <Plus className="h-3.5 w-3.5" />
            </button>
          )}
        </div>

        {showCreateForm && (
          <div className="mb-2 flex flex-col gap-1.5 rounded-lg bg-sidebar-hover p-2">
            <input
              type="text"
              value={newAlbumName}
              onChange={(e) => setNewAlbumName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleCreateAlbum();
                if (e.key === 'Escape') setShowCreateForm(false);
              }}
              placeholder={copy.albums.nameLabel}
              className="rounded border border-white/10 bg-sidebar px-2 py-1.5 text-xs text-sidebar-text-bright placeholder:text-sidebar-text/40 focus:border-brand-500 focus:outline-none"
              autoFocus
            />
            <input
              type="text"
              value={newAlbumDesc}
              onChange={(e) => setNewAlbumDesc(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleCreateAlbum();
                if (e.key === 'Escape') setShowCreateForm(false);
              }}
              placeholder={copy.albums.descriptionPlaceholder}
              className="rounded border border-white/10 bg-sidebar px-2 py-1.5 text-xs text-sidebar-text-bright placeholder:text-sidebar-text/40 focus:border-brand-500 focus:outline-none"
            />
            <div className="flex justify-end gap-1">
              <button
                type="button"
                onClick={() => setShowCreateForm(false)}
                className="rounded px-2 py-1 text-xs text-sidebar-text/60 hover:text-sidebar-text-bright"
              >
                {copy.common.cancel}
              </button>
              <button
                type="button"
                onClick={handleCreateAlbum}
                disabled={createAlbum.isPending || !newAlbumName.trim()}
                className="rounded bg-brand-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-500 disabled:opacity-50"
              >
                {copy.albums.create}
              </button>
            </div>
          </div>
        )}

        <nav className="flex flex-col gap-0.5">
          {albums.map((album) => (
            <div key={album.id} className="group flex items-center">
              {editingAlbumId === album.id ? (
                <div className="flex flex-1 items-center gap-1 rounded-lg bg-sidebar-hover p-1">
                  <input
                    type="text"
                    value={editingAlbumName}
                    onChange={(e) => setEditingAlbumName(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') handleSaveEdit();
                      if (e.key === 'Escape') setEditingAlbumId(null);
                    }}
                    className="min-w-0 flex-1 rounded border border-white/10 bg-sidebar px-2 py-1 text-xs text-sidebar-text-bright focus:border-brand-500 focus:outline-none"
                    autoFocus
                  />
                  <button
                    type="button"
                    onClick={handleSaveEdit}
                    className="rounded p-1 text-green-400 hover:bg-green-400/10"
                  >
                    <Check className="h-3 w-3" />
                  </button>
                  <button
                    type="button"
                    onClick={() => setEditingAlbumId(null)}
                    className="rounded p-1 text-sidebar-text/50 hover:text-sidebar-text-bright"
                  >
                    <X className="h-3 w-3" />
                  </button>
                </div>
              ) : (
                <>
                  <NavLink
                    to={`/albums/${album.id}`}
                    className={({ isActive }) =>
                      `flex flex-1 items-center justify-between rounded-lg px-3 py-1.5 text-sm transition-colors ${
                        isActive
                          ? 'bg-sidebar-active text-sidebar-text-bright'
                          : 'text-sidebar-text hover:bg-sidebar-hover hover:text-sidebar-text-bright'
                      }`
                    }
                  >
                    <span className="truncate">{album.name}</span>
                    <span className="ml-2 text-[11px] tabular-nums text-sidebar-text/40">
                      {album.photos_count}
                    </span>
                  </NavLink>
                  {isAuthenticated && (
                    <div className="hidden shrink-0 items-center gap-0.5 pr-1 group-hover:flex">
                      <button
                        type="button"
                        onClick={() => handleStartEdit(album.id, album.name)}
                        className="rounded p-1 text-sidebar-text/40 hover:text-sidebar-text-bright"
                        aria-label={copy.albums.edit}
                      >
                        <Pencil className="h-3 w-3" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDeleteAlbum(album.id)}
                        className="rounded p-1 text-sidebar-text/40 hover:text-red-400"
                        aria-label={copy.albums.delete}
                      >
                        <Trash2 className="h-3 w-3" />
                      </button>
                    </div>
                  )}
                </>
              )}
            </div>
          ))}
          {albums.length === 0 && !showCreateForm && (
            <p className="px-3 py-1 text-xs text-sidebar-text/40">{copy.albums.emptyState}</p>
          )}
        </nav>

        <div className="mx-0 my-3 border-t border-white/10" />

        {/* Tags */}
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-[11px] font-semibold uppercase tracking-widest text-sidebar-text/60">
            {copy.nav.tags}
          </h2>
          {isAuthenticated && (
            <button
              type="button"
              onClick={() => setShowTagInput((prev) => !prev)}
              className="rounded p-0.5 text-sidebar-text/50 transition-colors hover:text-sidebar-text-bright"
              aria-label={copy.tags.add}
            >
              <Plus className="h-3.5 w-3.5" />
            </button>
          )}
        </div>

        {showTagInput && (
          <div className="mb-2">
            <input
              type="text"
              value={newTagName}
              onChange={(e) => setNewTagName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleCreateTag();
                if (e.key === 'Escape') {
                  setShowTagInput(false);
                  setNewTagName('');
                }
              }}
              placeholder={copy.tags.placeholder}
              className="w-full rounded border border-white/10 bg-sidebar-hover px-2 py-1.5 text-xs text-sidebar-text-bright placeholder:text-sidebar-text/40 focus:border-brand-500 focus:outline-none"
              autoFocus
            />
            <p className="mt-1 text-[10px] text-sidebar-text/40">{copy.tags.createHint}</p>
          </div>
        )}

        <div className="flex flex-wrap gap-1.5">
          {tags.map((tag) => (
            <button
              key={tag.id}
              type="button"
              onClick={() => toggleTag(tag.slug)}
              aria-pressed={activeTags.includes(tag.slug)}
              className={`rounded-md px-2 py-1 text-xs font-medium transition-colors ${
                activeTags.includes(tag.slug)
                  ? 'bg-brand-600 text-white'
                  : 'bg-sidebar-hover text-sidebar-text hover:text-sidebar-text-bright'
              }`}
            >
              {tag.name}
            </button>
          ))}
          {tags.length === 0 && !showTagInput && (
            <p className="text-xs text-sidebar-text/40">No tags yet</p>
          )}
        </div>
      </section>

      {/* Stats bar */}
      <div className="border-t border-white/10 px-3 py-3">
        <div className="flex items-center justify-around text-center">
          <div className="flex flex-col items-center">
            <Camera className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
            <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
              {totalPhotos}
            </span>
            <span className="text-[10px] text-sidebar-text/50">{copy.stats.photos}</span>
          </div>
          <div className="flex flex-col items-center">
            <FolderOpen className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
            <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
              {albums.length}
            </span>
            <span className="text-[10px] text-sidebar-text/50">{copy.stats.albums}</span>
          </div>
          <div className="flex flex-col items-center">
            <Tag className="mb-0.5 h-3.5 w-3.5 text-sidebar-text/50" />
            <span className="text-sm font-semibold tabular-nums text-sidebar-text-bright">
              {tags.length}
            </span>
            <span className="text-[10px] text-sidebar-text/50">{copy.stats.tags}</span>
          </div>
        </div>
      </div>

      <ConfirmDialog
        isOpen={albumToDelete !== null}
        onClose={() => setAlbumToDelete(null)}
        onConfirm={handleConfirmDeleteAlbum}
        title={copy.albums.delete}
        message={copy.albums.deleteConfirm}
        confirmLabel={copy.common.delete}
        variant="danger"
        isPending={deleteAlbum.isPending}
      />
    </aside>
  );
}
