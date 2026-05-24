import { useState, useCallback } from 'react';
import { NavLink } from 'react-router-dom';
import { Plus, Pencil, Trash2, X, Check } from 'lucide-react';
import { toast } from 'sonner';
import { useAlbums, useCreateAlbum, useUpdateAlbum, useDeleteAlbum } from '../../hooks/useAlbums';
import { ConfirmDialog } from '../ConfirmDialog';
import { copy } from '../../data/copy';
import type { Album } from '../../types';

interface AlbumListProps {
  isAuthenticated: boolean;
}

export function AlbumList({ isAuthenticated }: AlbumListProps) {
  const { data: albumsData } = useAlbums();
  const createAlbum = useCreateAlbum();
  const updateAlbum = useUpdateAlbum();
  const deleteAlbum = useDeleteAlbum();

  const [showCreateForm, setShowCreateForm] = useState(false);
  const [newAlbumName, setNewAlbumName] = useState('');
  const [newAlbumDesc, setNewAlbumDesc] = useState('');
  const [editingAlbumId, setEditingAlbumId] = useState<string | null>(null);
  const [editingAlbumName, setEditingAlbumName] = useState('');
  const [albumToDelete, setAlbumToDelete] = useState<string | null>(null);

  const albums = albumsData?.data ?? [];

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

  const handleConfirmDeleteAlbum = useCallback(() => {
    if (!albumToDelete) return;
    deleteAlbum.mutate(albumToDelete, {
      onSuccess: () => setAlbumToDelete(null),
    });
  }, [deleteAlbum, albumToDelete]);

  return (
    <>
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
          <AlbumItem
            key={album.id}
            album={album}
            isAuthenticated={isAuthenticated}
            isEditing={editingAlbumId === album.id}
            editingName={editingAlbumName}
            onStartEdit={handleStartEdit}
            onSaveEdit={handleSaveEdit}
            onCancelEdit={() => setEditingAlbumId(null)}
            onDelete={setAlbumToDelete}
            onChangeEditingName={setEditingAlbumName}
          />
        ))}
        {albums.length === 0 && !showCreateForm && (
          <p className="px-3 py-1 text-xs text-sidebar-text/40">{copy.albums.emptyState}</p>
        )}
      </nav>

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
    </>
  );
}

interface AlbumItemProps {
  album: Album;
  isAuthenticated: boolean;
  isEditing: boolean;
  editingName: string;
  onStartEdit: (id: string, name: string) => void;
  onSaveEdit: () => void;
  onCancelEdit: () => void;
  onDelete: (id: string) => void;
  onChangeEditingName: (name: string) => void;
}

function AlbumItem({
  album,
  isAuthenticated,
  isEditing,
  editingName,
  onStartEdit,
  onSaveEdit,
  onCancelEdit,
  onDelete,
  onChangeEditingName,
}: AlbumItemProps) {
  if (isEditing) {
    return (
      <div className="flex flex-1 items-center gap-1 rounded-lg bg-sidebar-hover p-1">
        <input
          type="text"
          value={editingName}
          onChange={(e) => onChangeEditingName(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') onSaveEdit();
            if (e.key === 'Escape') onCancelEdit();
          }}
          className="min-w-0 flex-1 rounded border border-white/10 bg-sidebar px-2 py-1 text-xs text-sidebar-text-bright focus:border-brand-500 focus:outline-none"
          autoFocus
        />
        <button
          type="button"
          onClick={onSaveEdit}
          className="rounded p-1 text-green-400 hover:bg-green-400/10"
        >
          <Check className="h-3 w-3" />
        </button>
        <button
          type="button"
          onClick={onCancelEdit}
          className="rounded p-1 text-sidebar-text/50 hover:text-sidebar-text-bright"
        >
          <X className="h-3 w-3" />
        </button>
      </div>
    );
  }

  return (
    <div className="group flex items-center">
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
            onClick={() => onStartEdit(album.id, album.name)}
            className="rounded p-1 text-sidebar-text/40 hover:text-sidebar-text-bright"
            aria-label={copy.albums.edit}
          >
            <Pencil className="h-3 w-3" />
          </button>
          <button
            type="button"
            onClick={() => onDelete(album.id)}
            className="rounded p-1 text-sidebar-text/40 hover:text-red-400"
            aria-label={copy.albums.delete}
          >
            <Trash2 className="h-3 w-3" />
          </button>
        </div>
      )}
    </div>
  );
}
