import type { PhotoExif } from '../types';
import { Info } from 'lucide-react';
import { copy } from '../data/copy';

interface ExifPanelProps {
  exif: PhotoExif | null;
}

interface ExifRow {
  label: string;
  value: string | number | null;
}

export function ExifPanel({ exif }: ExifPanelProps) {
  if (!exif) {
    return (
      <div className="flex h-full flex-col items-center justify-center gap-2 p-6 text-gray-400">
        <Info className="h-8 w-8" />
        <p className="text-sm">{copy.exif.noData}</p>
      </div>
    );
  }

  const rows: ExifRow[] = [
    { label: copy.exif.camera, value: exif.camera },
    { label: copy.exif.iso, value: exif.iso },
    { label: copy.exif.aperture, value: exif.aperture },
    { label: copy.exif.shutter, value: exif.shutter },
    { label: copy.exif.focalLength, value: exif.focal_length },
    { label: copy.exif.takenAt, value: exif.taken_at },
  ];

  return (
    <div className="flex flex-col gap-3 p-4">
      <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-300">{copy.exif.title}</h3>
      <dl className="flex flex-col gap-2">
        {rows.map((row) => (
          <div key={row.label} className="flex flex-col gap-0.5">
            <dt className="text-xs text-gray-400">{row.label}</dt>
            <dd className="text-sm text-white">{row.value ?? '—'}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
