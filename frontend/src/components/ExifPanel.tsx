import type { PhotoExif } from '../types';
import { Info } from 'lucide-react';

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
        <p className="text-sm">No EXIF data</p>
      </div>
    );
  }

  const rows: ExifRow[] = [
    { label: 'Camera', value: exif.camera },
    { label: 'ISO', value: exif.iso },
    { label: 'Aperture', value: exif.aperture },
    { label: 'Shutter Speed', value: exif.shutter },
    { label: 'Focal Length', value: exif.focal_length },
    { label: 'Taken At', value: exif.taken_at },
  ];

  return (
    <div className="flex flex-col gap-3 p-4">
      <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-300">EXIF Data</h3>
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
