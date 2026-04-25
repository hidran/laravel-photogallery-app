# SPEC.md Validation — PhotoGallery Pro

> Comprehension check performed against `SPEC.md`. Every answer below maps to a specific section and was re-read from source (not recalled). Date: 2026-04-25.

---

## 1. Database tables — 9 total

| # | Table | Columns | Section |
|---|---|---|---|
| 1 | `users` | 8 (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) | §4.1 |
| 2 | `albums` | 6 (id, name, description, cover_photo_id, created_at, updated_at) | §4.2 |
| 3 | `tags` | 5 (id, name, slug, created_at, updated_at) | §4.3 |
| 4 | `photos` | 20 (id, title, description, filename, original_path, thumbnail_path, medium_path, large_path, album_id, width, height, file_size, mime_type, is_favorite, exif, processing_status, processing_attempts, processing_error, created_at, updated_at) | §4.4 |
| 5 | `photo_tag` | 3 (photo_id, tag_id, created_at) — composite PK | §4.5 |
| 6 | `personal_access_tokens` | 10 (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) | §4.6 |
| 7 | `jobs` | 7 (id, queue, payload, attempts, reserved_at, available_at, created_at) | §4.7 |
| 8 | `failed_jobs` | 7 (id, uuid, connection, queue, payload, exception, failed_at) | §4.7 |
| 9 | `job_batches` | 10 (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) | §4.8 |

5 application tables + 4 framework tables (Sanctum + queue infra).

---

## 2. API endpoints — 18 total

**Auth (§6.1, 4):**
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`

**Photos (§6.2, 8):**
- `GET /photos`
- `GET /photos/{id}`
- `POST /photos`
- `PATCH /photos/{id}`
- `DELETE /photos/{id}`
- `POST /photos/{id}/favorite`
- `POST /photos/batch`
- `GET /photos/batch/{batchId}`

**Albums (§6.3, 5):**
- `GET /albums`
- `GET /albums/{id}`
- `POST /albums`
- `PATCH /albums/{id}`
- `DELETE /albums/{id}`

**Tags (§6.4, 1):**
- `GET /tags`

There is no `POST /tags` in v1 — tag creation is implicit via photo create/update/batch.

---

## 3. Filament resources and widgets

- **3 resources:** `PhotoResource`, `AlbumResource`, `TagResource` (§7.1–§7.3)
- **3 widgets:** `StatsOverview`, `RecentUploadsTable`, `QueueMonitor` (§7.4)
- **1 custom page** (not counted as widget): `StorageManagement` under nav group "System" (§7.5)

---

## 4. Three image sizes (§11.2)

| Size | Width | Height | Quality | Format |
|---|---|---|---|---|
| thumbnail | 300 px | auto (aspect-preserved) | 80 | JPEG |
| medium | 800 px | auto | 85 | JPEG |
| large | 1600 px | auto | 90 | JPEG |

Never upscale: if source width < target, the source is stored as that size.

---

## 5. Six keyboard shortcuts (§8.8)

| Key | Action |
|---|---|
| `←` | Previous photo (lightbox open) |
| `→` | Next photo (lightbox open) |
| `Escape` | Close lightbox/modal |
| `F` | Toggle favorite on current photo |
| `Delete` | Delete current photo (with confirm) |
| `/` | Focus search input |

All ignored when focus is in `<input>`/`<textarea>`/`[contenteditable]` **except `/`**, which steals focus.

---

## 6. Queue driver

- **Local dev:** `QUEUE_CONNECTION=database` — uses the `jobs` and `failed_jobs` tables (§2, §11.4).
- **Production:** `QUEUE_CONNECTION=sqs` — AWS SQS standard queue. Pending jobs live in SQS; only `failed_jobs` table is used in prod (§4.7).

---

## 7. Storage driver

- **Local dev:** local filesystem at `storage/app/public/photos/`, exposed via `php artisan storage:link`. `PHOTOS_DISK=public` (§2, §11.4).
- **Production:** AWS S3 via `league/flysystem-aws-s3-v3`. `PHOTOS_DISK=s3` with `AWS_BUCKET`, `AWS_URL` (CDN), `AWS_DEFAULT_REGION` etc.

In both environments code calls `Storage::disk('photos')` — the disk resolves to `local` or `s3` based on `PHOTOS_DISK`. No code changes between envs.

---

## 8. Photo upload — full flow (§11.1 → §11.2 → §8.7)

1. Client sends `POST /api/v1/photos` (or `/photos/batch`) as `multipart/form-data` with field `photo` (file), plus optional `title`, `description`, `album_id`, `tags[]`, `is_favorite`. Authorization: Bearer token required.
2. `StorePhotoRequest` validates: `image|mimes:jpg,jpeg,png,webp|max:10240`. Failures → `415` (bad MIME / not multipart), `413` (too large), or `422`.
3. Controller saves the original synchronously: `Storage::disk('photos')->putFileAs('originals', $file, $uuid.'.'.$ext)`.
4. `Photo` row inserted with `processing_status='pending'`, `processing_attempts=0`, `file_size`, `mime_type`, `filename`, `original_path`. Tags upserted by name; album associated if provided.
5. `ProcessPhoto::dispatch($photo)` queued (database driver locally, SQS in prod).
6. Controller returns `201 Created` with `{data: Photo}`. `urls.thumbnail`/`medium`/`large`, `width`, `height`, `exif` are all `null` at this moment; `urls.original` is populated.
7. Async worker picks up the job (`tries=3`, `backoff=[10,30,60]`):
   - Sets `processing_status='processing'`, saves.
   - Loads original via `Storage::disk('photos')`.
   - `ImageProcessor` (Intervention Image v3) generates thumbnail/medium/large per the size table; auto-orient from EXIF; never upscales; writes to `photos/thumbnails/`, `photos/medium/`, `photos/large/` as JPEG.
   - `ExifExtractor` extracts `camera`, `iso`, `aperture`, `shutter`, `focal_length`, `taken_at`; stored in `exif` JSON column.
   - Updates `width`, `height`, the three `*_path` columns; sets `processing_status='completed'`; saves.
   - On exception: after 3 attempts, `failed()` writes `processing_status='failed'` and `processing_error`.
8. Frontend `useProcessingPoll` polls `GET /photos/:id` every 3 s; once `processing_status` is `completed` or `failed` it invalidates the `['photos']` query so the masonry grid re-fetches and the new tile appears with real thumbnails. (For batches, the frontend also polls `GET /photos/batch/:id` every 1 s for overall progress.)

---

## 9. Deleting an album vs deleting its photos (§4.2, §4.4, §5, §6.3)

**`DELETE /albums/{id}`:**
- Album row is deleted.
- **Photos are NOT deleted.** The FK `photos.album_id → albums(id)` has **`ON DELETE SET NULL`**, so every photo that belonged to the album has its `album_id` set to `NULL` and survives as an unassigned photo.
- Files on disk are untouched.
- Response: `204 No Content`.

**Contrast with `DELETE /photos/{id}`:**
- Photo row deleted.
- `photo_tag` rows for that photo are removed by **`ON DELETE CASCADE`** (tags themselves survive).
- If the photo was an album cover, the FK `albums.cover_photo_id → photos(id)` has **`ON DELETE SET NULL`**, so the album's `cover_photo_id` becomes `NULL`; the album survives.
- `PhotoObserver::deleted()` deletes all 4 files (original + thumbnail + medium + large) from `Storage::disk('photos')`.
- Response: `204 No Content`.

---

## 10. Monthly/yearly toggle

Not in this spec. PhotoGallery Pro has no pricing, no billing tier, no plan toggle — it's a self-hosted gallery, not a SaaS. Searching for "monthly", "yearly", "subscription", "plan", "pricing", or "billing" in `SPEC.md` returns zero matches. That feature belongs to a different project (likely the landing-page repo at `../../landing-page/`). Confirmed: answering against PhotoGallery Pro's `SPEC.md`, not cross-contaminating.

---

**Validation status:** clean. Every answer above maps to a specific section of `SPEC.md`.
