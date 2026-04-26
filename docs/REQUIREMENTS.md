# PhotoGallery Pro — Requirements

> The business layer. **What** we're building, in plain language. Suitable for a non-technical stakeholder. No code, no schemas, no technical decisions — those live in `DESIGN.md`.

---

## 1. Vision

PhotoGallery Pro is a personal-to-small-team photo gallery: a place to upload, organize, find, and view photographs. It looks and feels like a curated portfolio — masonry tiles on the gallery page, a fullscreen lightbox with keyboard navigation, and an admin panel for content management. It runs equally well as a single-user gallery on a developer's laptop and as a multi-user deployment on AWS.

**What it is not** (v1, intentionally):
- Not a social network — no comments, likes, follows, public feeds beyond the gallery itself.
- Not a stock-photo store — no licensing, no purchasing, no watermarks.
- Not a desktop client — browser-only. No native iOS/Android app.
- Not a versioning system — uploaded files are immutable; updates replace metadata only.

---

## 2. Personas

### P1 — Visitor (anonymous)
A person browsing the public gallery. Reads only. Wants to find and view photos quickly without registering.

### P2 — Member (authenticated user)
A registered user who uploads, organizes, and curates their own collection. Owns their photos and albums; cannot modify other members' content.

### P3 — Administrator
A staff user with elevated access to the admin panel. Manages content across all members, monitors processing health, frees disk space.

---

## 3. User stories

### Browsing (Visitor + Member + Administrator)
- **US-01.** As a visitor, I want to see all public photos in a masonry grid so I can scan many at once.
- **US-02.** As a visitor, I want to type into a search box and see matching photos appear without reloading the page.
- **US-03.** As a visitor, I want to click a tag and see only photos with that tag.
- **US-04.** As a visitor, I want to combine multiple tag filters so I can narrow results to photos that match every selected tag.
- **US-05.** As a visitor, I want to filter by album so I can browse a specific collection.
- **US-06.** As a visitor, I want to sort photos by newest, oldest, alphabetical, or favorites-first.
- **US-07.** As a visitor, I want to scroll continuously without clicking "Next page".
- **US-08.** As a visitor, I want to click a thumbnail and see the full-size photo with EXIF details.
- **US-09.** As a visitor, I want to navigate to the next/previous photo using arrow keys or on-screen arrows.
- **US-10.** As a visitor, I want to share a link to a specific photo (URL contains the photo id).
- **US-11.** As a visitor, I want to dismiss the lightbox with the Escape key.
- **US-12.** As a visitor on a phone, I want the gallery to render as 2 columns; on a tablet 3; on a desktop 4.

### Upload & ownership (Member)
- **US-13.** As a member, I want to drag and drop photos onto a target zone to upload them.
- **US-14.** As a member, I want to upload between 1 and 20 photos at once.
- **US-15.** As a member, I want each uploaded photo to show a per-file progress bar.
- **US-16.** As a member, I want to see a placeholder tile in the gallery while a photo is processing, and have it transition to the real thumbnail when done.
- **US-17.** As a member, I want the upload to fail loudly (with a clear message) if a file is over 10 MB or in an unsupported format — before any bytes leave my browser.
- **US-18.** As a member, I want to set a title, description, album, and tags either at upload or later.
- **US-19.** As a member, I want only my own photos and albums to appear when I edit or delete; other members' content is read-only to me.

### Organization (Member)
- **US-20.** As a member, I want to mark any photo as a favorite with one click and see it in a "Favorites" view.
- **US-21.** As a member, I want to create albums to group related photos.
- **US-22.** As a member, I want to set an album cover photo.
- **US-23.** As a member, I want to add multiple tags per photo, including new tags I create on the fly.
- **US-24.** As a member, I want existing tags to autocomplete when I'm tagging a new photo.

### Editing (Member)
- **US-25.** As a member, I want to edit the title, description, tags, and album of a photo from inside the lightbox without opening another page.
- **US-26.** As a member, I want to delete a photo from the lightbox; the system should ask me to confirm.
- **US-27.** As a member, I want my private original photo (full resolution + full EXIF) to remain accessible only to me — never to other visitors, even with a direct URL.

### Keyboard power-user (Member + Administrator)
- **US-28.** As a power user, I want a `/` shortcut to focus the search box from anywhere.
- **US-29.** As a power user, I want `←`/`→` to navigate the lightbox, `Escape` to close, `F` to favorite, `Delete` to delete.

### Authentication (Member + Administrator)
- **US-30.** As a member, I want to register with email, name, and password.
- **US-31.** As a member, I want to log in and have my session persist for 24 hours of inactivity.
- **US-32.** As a member, I want to log out and have my session immediately revoked across the application.
- **US-33.** As an administrator, I want to log into a separate admin panel with a different URL.

### Admin panel (Administrator)
- **US-34.** As an administrator, I want a dashboard showing total photos, albums, tags, and storage used.
- **US-35.** As an administrator, I want to see the last 10 uploads at a glance.
- **US-36.** As an administrator, I want to see how many background jobs are pending and how many have failed.
- **US-37.** As an administrator, I want to bulk-reprocess photos whose thumbnails failed to generate.
- **US-38.** As an administrator, I want to view, edit, or delete any photo, album, or tag in the system.
- **US-39.** As an administrator, I want to bulk-assign photos to an album, bulk-toggle favorites, and bulk-delete.
- **US-40.** As an administrator, I want a storage page that shows disk usage broken down by image size, with a button to regenerate all thumbnails.

---

## 4. Feature catalog (grouped)

### F1 — Public gallery
- F1.1 Masonry grid (responsive: 2/3/4 columns)
- F1.2 Lazy-loaded thumbnails with no layout shift
- F1.3 Infinite scroll (cursor-based; no page numbers)
- F1.4 Per-photo hover overlay: title + favorite indicator
- F1.5 Empty state with call-to-action when no photos match

### F2 — Search & filtering
- F2.1 Free-text search across title and description (debounced 300 ms)
- F2.2 Tag filtering with AND logic (photo must have every selected tag)
- F2.3 Album filtering
- F2.4 Favorites-only filter
- F2.5 Sort: newest / oldest / alphabetical / favorites-first
- F2.6 Filter state reflected in the URL (shareable, back-button-friendly)

### F3 — Lightbox
- F3.1 Fullscreen overlay with darkened background
- F3.2 Large rendition of the photo with auto-prefetch of next/previous
- F3.3 Inline-editable title, description, tags (owner only)
- F3.4 EXIF panel: camera, ISO, aperture, shutter, focal length, taken-at (no GPS)
- F3.5 "View full size" link (owner only — produces a time-limited signed URL)
- F3.6 Delete with confirmation
- F3.7 Linkable via URL (?photo=...)
- F3.8 Closes on Escape, backdrop click, or URL change

### F4 — Upload
- F4.1 Drag-and-drop zone + click-to-browse
- F4.2 Client-side validation: MIME (JPG/PNG/WebP), size (≤ 10 MB)
- F4.3 Up to 20 files per submission
- F4.4 Per-file progress bar
- F4.5 Set album, tags, title at upload time (or accept defaults)
- F4.6 Upload returns immediately; processing happens in the background

### F5 — Processing pipeline
- F5.1 Three sizes generated per photo (thumbnail / medium / large)
- F5.2 EXIF extracted; GPS stripped; remaining EXIF stored as JSON
- F5.3 Live status visible in the gallery (pending → processing → completed / failed)
- F5.4 Failed jobs retry up to 3 times with exponential backoff
- F5.5 Members can see "processing failed" overlays on their own photos and request reprocessing (admin action)

### F6 — Albums
- F6.1 Member creates / renames / deletes own albums
- F6.2 Album has unique name per member (two members can both have "Travel")
- F6.3 Album can have a cover photo
- F6.4 Deleting an album does NOT delete its photos (they become unassigned)
- F6.5 Photo-count visible on each album

### F7 — Tags
- F7.1 Tags are global (shared across members)
- F7.2 Tags are created implicitly when a member uses a new name
- F7.3 Slug auto-generated from the name; uniqueness collisions resolved with `-2`, `-3`, …
- F7.4 Photo-count visible per tag
- F7.5 Tags cannot be deleted via the API (admin-only via the panel)

### F8 — Favorites
- F8.1 Per-member favorites are not in v1 — favorite is a property of the photo, owned-by-photo-owner
- F8.2 One click to mark / unmark
- F8.3 Optimistic UI (heart fills before the request returns)
- F8.4 Dedicated "Favorites" view in the sidebar

### F9 — Authentication
- F9.1 Email + password registration
- F9.2 Login returns a 24-hour session token
- F9.3 Logout revokes the session token immediately
- F9.4 Public read access requires no login
- F9.5 All write operations require login

### F10 — Authorization
- F10.1 Members can only edit/delete photos and albums they own
- F10.2 Tags are global and not user-owned
- F10.3 Administrators bypass ownership checks
- F10.4 Original-resolution files are never publicly accessible — only via short-lived signed URLs to the owner or an admin

### F11 — Admin panel
- F11.1 Separate URL (`/admin`)
- F11.2 Photo / Album / Tag content management with table + form views
- F11.3 Filters: album, tag, favorites, date range, processing status
- F11.4 Bulk actions: delete, assign to album, toggle favorite, reprocess
- F11.5 Dashboard widgets: stats overview, recent uploads, queue health
- F11.6 Storage management page

### F12 — Reliability & monitoring
- F12.1 Queue health visible in the admin panel
- F12.2 Failed jobs retained for inspection
- F12.3 Storage usage broken down by image size
- F12.4 Manual "regenerate all thumbnails" capability
- F12.5 Health endpoint suitable for an uptime monitor

---

## 5. Acceptance criteria (testable)

Each feature ships with verifiable conditions. Pull these into Pest, Vitest, and Playwright tests.

### F1 — Public gallery
- AC-F1-1: With 30 seeded photos, `GET /` renders 24 tiles and displays a "Load more" sentinel that triggers fetching of page 2 on intersection.
- AC-F1-2: Resizing the viewport to 375 px wide renders 2 columns; 768 px renders 3; 1280 px renders 4.
- AC-F1-3: A photo with width=4000, height=3000 reserves space at a 4:3 aspect ratio before its thumbnail loads (no layout shift).
- AC-F1-4: With zero photos, the gallery renders an empty-state component containing the text "No photos yet" and a button labeled "Upload photos".

### F2 — Search & filtering
- AC-F2-1: Typing "sunset" in the search box triggers exactly one network request 300 ms after the user stops typing.
- AC-F2-2: Selecting tags ["sunset", "beach"] returns only photos that have BOTH tags (AND logic).
- AC-F2-3: The URL `/?search=mountain&tags%5B%5D=hike&sort=oldest` reproduces the same filtered view on reload.
- AC-F2-4: Sort option "favorites-first" places all favorite photos before non-favorites; within each group, newest first.

### F3 — Lightbox
- AC-F3-1: Clicking a thumbnail sets `?photo=<id>` and opens the lightbox without a page reload.
- AC-F3-2: Pressing `→` advances to the next photo in the current filtered list; at the end, it stops (no wrap).
- AC-F3-3: Pressing `Escape` closes the lightbox and removes `?photo=` from the URL.
- AC-F3-4: For a photo whose owner is the logged-in user, the "View full size" button is visible. For non-owners, it is hidden.
- AC-F3-5: A photo with no EXIF shows the EXIF panel empty state "No EXIF data available".

### F4 — Upload
- AC-F4-1: Dragging a 15 MB JPEG into the dropzone displays inline error "File exceeds 10 MB" and does not initiate the upload.
- AC-F4-2: Uploading a `.txt` file shows "Only JPG, PNG, or WebP supported" and refuses to send it.
- AC-F4-3: Submitting 21 files shows "Up to 20 files at once" and refuses to send.
- AC-F4-4: A successful upload of 5 files within 10 seconds returns a batch identifier and a list of 5 photo ids; each photo appears in the gallery with a "processing" overlay.

### F5 — Processing pipeline
- AC-F5-1: After a photo is processed, three thumbnails are stored on the public disk and the original on the private disk.
- AC-F5-2: A photo's `exif` field contains `camera`, `iso`, `aperture`, `shutter`, `focal_length`, `taken_at` keys when present in the source EXIF; never `GPSLatitude`, `GPSLongitude`, or `GPSAltitude`.
- AC-F5-3: A processing failure is retried up to 3 times with backoff [10 s, 30 s, 60 s] before being marked `failed`.
- AC-F5-4: A photo whose original is 200 px wide produces three "sizes" — all 200 px wide (never upscaled).

### F6 — Albums
- AC-F6-1: Two different members can each create an album named "Travel"; the system accepts both.
- AC-F6-2: A single member creating two albums named "Travel" gets a 422 with the second attempt.
- AC-F6-3: Deleting an album of 10 photos leaves 10 photos in the gallery, unassigned to any album.

### F7 — Tags
- AC-F7-1: Two members tagging a photo as "sunset" reference the same tag id.
- AC-F7-2: Creating a tag named "Sunset Photography" produces slug `sunset-photography`.
- AC-F7-3: Creating a second tag named "Sunset Photography" produces a name collision error (admin-only override).

### F8 — Favorites
- AC-F8-1: `PUT /photos/{id}/favorite` is idempotent: two consecutive calls both return 204 and leave `is_favorite=true`.
- AC-F8-2: Marking a photo as favorite from the gallery flips the heart icon before the network round trip completes (optimistic).

### F9 — Authentication
- AC-F9-1: A registered member's token is valid for exactly 1440 minutes (24 hours) from issuance.
- AC-F9-2: After `POST /auth/logout`, the previously valid token returns 401 on any subsequent request.
- AC-F9-3: Anonymous `GET /photos` returns 200 with public photo data; anonymous `POST /photos` returns 401.

### F10 — Authorization
- AC-F10-1: Member A trying to PATCH member B's photo receives 403.
- AC-F10-2: A direct request for an original file URL without auth returns 403 (or fails to resolve the path).
- AC-F10-3: An administrator can edit any photo regardless of owner.

### F11 — Admin panel
- AC-F11-1: A regular member visiting `/admin` is redirected to a login page; their member token does not grant access.
- AC-F11-2: The dashboard "Photos Processing" stat decreases as jobs complete (verified within 5 s of completion).
- AC-F11-3: Bulk-selecting 10 photos and clicking "Reprocess" enqueues 10 `ProcessPhoto` jobs and shows a success toast.

### F12 — Reliability & monitoring
- AC-F12-1: With the queue worker stopped, uploading a photo causes the dashboard "Pending Jobs" count to increment within 2 s.
- AC-F12-2: A `GET /api/v1/health` endpoint returns 200 with body `{ "status": "ok", "storage": "ok", "queue": "ok" }` when all systems are healthy.

---

## 6. Constraints

### File constraints
- Allowed photo formats: **JPG, JPEG, PNG, WebP** (image/jpeg, image/png, image/webp).
- Maximum file size per photo: **10 MB** (10,240 KB).
- Minimum source dimension (informational, not enforced): there is no minimum — small images are stored as-is and never upscaled.
- Maximum source dimension (informational): `PHOTOS_MAX_DIMENSION` env, default 8000 px on the longest side.

### Batch constraints
- Maximum files per upload submission: **20**.
- Minimum: **1** (a single-file upload uses the same endpoint as a 20-file batch).

### Naming constraints
- Photo titles: 1–255 characters.
- Photo descriptions: 0–5000 characters.
- Album names: 1–255 characters; unique per member.
- Tag names: 1–100 characters; globally unique.

### Account constraints
- Email: standard RFC-5322; unique across the system.
- Password: minimum 8 characters at registration; no maximum.

---

## 7. Non-functional requirements

### NFR-1 — Performance
- **Upload response** ≤ 1 second for any single ≤ 10 MB file (acceptance returns before processing).
- **Gallery time-to-first-tile** ≤ 1.5 seconds on a 5 Mbps connection with 30 photos seeded.
- **Lightbox open** ≤ 200 ms after click on a desktop browser.
- **Search** result update ≤ 600 ms after the debounce window (300 ms debounce + ≤ 300 ms response).
- **Lighthouse Performance score** ≥ 90 on desktop, ≥ 75 on mobile, on the gallery page with 30 photos.

### NFR-2 — Scalability targets (v1)
- Up to **10,000 photos** in the gallery without query-time degradation (cursor pagination).
- Up to **100 concurrent users** browsing.
- Up to **10 concurrent uploads** processed by a single queue worker.

### NFR-3 — Security
- Originals are **never** publicly accessible. Even with a leaked CDN URL, an attacker should not be able to download a member's full-resolution photo.
- **GPS EXIF** must be stripped before originals are stored and before any EXIF JSON is returned by the API.
- Member tokens expire after **24 hours** of inactivity.
- Strict **Content Security Policy** in production: no third-party scripts, no inline event handlers, no `unsafe-eval`.
- All passwords stored using **bcrypt** (Laravel default).
- Tokens never appear in URLs or logs.
- Rate limits: `/auth/*` ≤ 10 requests/minute per IP; all other endpoints ≤ 120 requests/minute per IP and ≤ 300 requests/minute per authenticated member.

### NFR-4 — Accessibility
- **WCAG 2.1 AA** compliance on the gallery and lightbox.
- Every interactive element reachable by keyboard with a visible focus indicator.
- Modal and lightbox **trap focus** while open and restore focus on close.
- Every `<img>` has alt text.
- Color contrast ≥ 4.5:1 for body text, 3:1 for large text.
- `prefers-reduced-motion` honored — non-essential transitions disabled.
- **axe-core** Playwright check passes with zero critical/serious findings.

### NFR-5 — Reliability
- The processing pipeline retries up to 3 times with backoff before marking a photo failed.
- A photo whose processing fails remains in the database; an admin can trigger a reprocess.
- Loss of the queue worker is recoverable: jobs persist; restarting the worker resumes processing.
- Backups: daily database snapshot retained for 30 days; S3 versioning enabled on both buckets.

### NFR-6 — Internationalization (out of scope for v1)
- All UI text lives in `frontend/src/data/copy.ts` so future i18n is feasible — but v1 ships English only.

### NFR-7 — Browser support
- Latest two versions of Chrome, Firefox, Safari, Edge.
- iOS Safari 16+, Android Chrome 110+.
- Internet Explorer not supported.

### NFR-8 — Observability
- Structured logs for every API request (method, path, status, latency, user id when authenticated).
- Failed processing jobs logged to a dedicated channel with full stack trace.
- Health endpoint suitable for an uptime monitor.

---

## 8. Out of scope (v1)

These are deliberately excluded to keep v1 small and shippable:
- Comments / likes / reactions
- Public sharing beyond the gallery page (no shareable "public album" links with custom permissions)
- Multi-device sync (all sessions are independent tokens)
- iOS/Android native apps
- Image editing (crop, rotate, filter) — except auto-orientation from EXIF on upload
- Watermarking
- PDF / video / RAW format support
- Multi-language UI
- Two-factor authentication
- Soft-delete / restore (deletes are permanent)
- Per-member quota enforcement (an admin-set hard limit)
- Tag hierarchies / categories / colors
- Custom album sorting (photos within an album sort by upload date)
- Bulk download
- Export / migration tools

These may return in v2 if the v1 deployment validates the core experience.
