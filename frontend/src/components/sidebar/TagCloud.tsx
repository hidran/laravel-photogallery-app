import { useState, useCallback } from 'react';
import { Plus } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { useTags } from '../../hooks/useTags';
import { copy } from '../../data/copy';

interface TagCloudProps {
  isAuthenticated: boolean;
}

export function TagCloud({ isAuthenticated }: TagCloudProps) {
  const { data: tagsData } = useTags();
  const [searchParams, setSearchParams] = useSearchParams();
  const [newTagName, setNewTagName] = useState('');
  const [showTagInput, setShowTagInput] = useState(false);

  const tags = tagsData?.data ?? [];
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

  return (
    <>
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
    </>
  );
}
