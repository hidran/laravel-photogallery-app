# PhotoGallery Pro — Technical Specification (SPEC.md)

> A full-stack photo gallery application with a Laravel 13 API, Filament v5 admin panel, and a React 19 + Vite 6 + Tailwind CSS v4 single-page frontend.

---

## 1. Project Overview

**PhotoGallery Pro** is a web-based photo gallery that lets users upload, organize, search, and view photos in a rich visual interface. It is composed of three logical layers built as two separate deployable projects:

- **Backend API (Laravel 13):** RESTful JSON API that handles persistence, authentication, file uploads, and background image processing.
- **Admin Panel (Filament v5):** Mounted inside the same Laravel app at `/admin`. Provides full content management for photos, albums, tags, and users, plus dashboard widgets and storage management.
- **Frontend SPA (React 19 + Vite 6 + Tailwind v4):** A public-facing single-page gallery application that consumes the API. Supports masonry grid, lightbox, keyboard navigation, drag-and-drop upload, and live processing status.

### Primary user stories
- **Visitor:** Browse the gallery, search/filter photos, open the lightbox, navigate with keyboard.
- **Authenticated user:** Upload photos (single or batch of up to 20), edit metadata, toggle favorites, manage albums.
- **Admin:** Log into Filament admin to manage all entities, view queue/storage dashboards, bulk-reprocess images.

### Non-functional requirements
- Mobile-first responsive design (masonry grid: 2 columns mobile, 3 tablet, 4 desktop).
- Image processing must be asynchronous — API responses must not block on resizing.
- Environment parity: local dev (DB queue + local disk) and production (SQS + S3) share the same code.
- UUIDs everywhere to avoid ID enumeration.
- All frontend text content lives in `src/data/` (no hardcoded strings in components).

---

## 2. Tech Stack

| Layer | Technology | Version | Notes |
|---|---|---|---|
| Backend Runtime | PHP | 8.3+ | Strict types where practical |
| Backend Framework | Laravel | 13.x | |
| Admin Panel | Filament | v5.x | Mounted at `/admin` |
| Auth (SPA) | Laravel Sanctum | 4.x | API tokens (not cookie mode) |
| Auth (Admin) | Laravel session | built-in | Standard `web` guard |
| Image Processing | Intervention Image | v3.x | `intervention/image-laravel` adapter |
| Queue (dev) | Database driver | — | `jobs` + `failed_jobs` tables |
| Queue (prod) | AWS SQS | — | `laravel/framework` built-in driver |
| Storage (dev) | Local disk | — | `storage/app/public` + `php artisan storage:link` |
| Storage (prod) | AWS S3 | — | `league/flysystem-aws-s3-v3` |
| Testing (backend) | Pest | 3.x | Feature + unit |
| Frontend Runtime | Node.js | 20 LTS+ | |
| Frontend Framework | React | 19.x | Function components + hooks only |
| Bundler | Vite | 6.x | `@vitejs/plugin-react` |
| Styling | Tailwind CSS | v4.x | **`@tailwindcss/vite` plugin + `@import "tailwindcss"` only** |
| Routing | React Router | 7.x | Data router API |
| HTTP client | Axios | 1.x | With interceptors for auth + errors |
| State (server) | TanStack Query | 5.x | For API caching, polling, invalidation |
| Icons | lucide-react | latest | |
| Toasts | sonner | latest | |
| Testing (frontend) | Vitest + React Testing Library | latest | |
| E2E | Playwright | latest | |

> **Tailwind v4 enforcement:** NO `tailwind.config.js`, NO `postcss.config.js`, NO `autoprefixer`. Configure via `@theme` inside `src/index.css` only.

---

## 3. Folder / File Architecture

### 3.1 Monorepo root

```
photogallerypro/
├── backend/          # Laravel application (API + Filament)
├── frontend/         # React SPA
├── docs/
│   └── PROMPT.md
└── SPEC.md
```

### 3.2 Backend (Laravel)

```
backend/
├── app/
│   ├── Enums/
│   │   └── ProcessingStatus.php            # pending | processing | completed | failed
│   ├── Filament/
│   │   ├── Pages/
│   │   │   └── StorageManagement.php       # disk usage + regenerate button
│   │   ├── Resources/
│   │   │   ├── AlbumResource.php
│   │   │   ├── AlbumResource/
│   │   │   │   ├── Pages/
│   │   │   │   │   ├── ListAlbums.php
│   │   │   │   │   ├── CreateAlbum.php
│   │   │   │   │   └── EditAlbum.php
│   │   │   │   └── RelationManagers/
│   │   │   │       └── PhotosRelationManager.php
│   │   │   ├── PhotoResource.php
│   │   │   ├── PhotoResource/Pages/{ListPhotos,CreatePhoto,EditPhoto,ViewPhoto}.php
│   │   │   └── TagResource.php
│   │   └── Widgets/
│   │       ├── StatsOverview.php
│   │       ├── RecentUploadsTable.php
│   │       └── QueueMonitor.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/
│   │   │       ├── AlbumController.php
│   │   │       ├── AuthController.php
│   │   │       ├── BatchUploadController.php
│   │   │       ├── PhotoController.php
│   │   │       └── TagController.php
│   │   ├── Requests/
│   │   │   ├── Album/{StoreAlbumRequest,UpdateAlbumRequest}.php
│   │   │   ├── Auth/{LoginRequest,RegisterRequest}.php
│   │   │   └── Photo/{StorePhotoRequest,UpdatePhotoRequest,BatchUploadRequest}.php
│   │   ├── Resources/
│   │   │   ├── AlbumResource.php
│   │   │   ├── PhotoResource.php
│   │   │   └── TagResource.php
│   │   └── Middleware/
│   │       └── ForceJsonResponse.php
│   ├── Jobs/
│   │   ├── ProcessPhoto.php                # single-photo pipeline job
│   │   └── ProcessBatchUpload.php          # orchestrates multi-file batch
│   ├── Models/
│   │   ├── Album.php
│   │   ├── Photo.php
│   │   ├── Tag.php
│   │   └── User.php
│   ├── Observers/
│   │   └── PhotoObserver.php               # auto-delete files on model delete
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── Filament/AdminPanelProvider.php
│   └── Services/
│       ├── ImageProcessor.php              # wraps Intervention Image v3
│       ├── ExifExtractor.php
│       └── StorageManager.php              # disk usage calculations
├── bootstrap/app.php                       # route + middleware registration (L11+ style)
├── config/
│   ├── filament.php
│   ├── filesystems.php                     # 'photos' disk (local|s3 via env)
│   └── sanctum.php
├── database/
│   ├── factories/{PhotoFactory,AlbumFactory,TagFactory}.php
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2026_01_01_000001_create_albums_table.php              # no cover_photo_id FK yet
│   │   ├── 2026_01_01_000002_create_tags_table.php
│   │   ├── 2026_01_01_000003_create_photos_table.php              # album_id FK → albums
│   │   ├── 2026_01_01_000004_add_cover_photo_fk_to_albums.php     # resolves circular FK
│   │   ├── 2026_01_01_000005_create_photo_tag_table.php
│   │   ├── 2026_01_01_000006_create_personal_access_tokens_table.php
│   │   ├── 2026_01_01_000007_create_jobs_table.php
│   │   ├── 2026_01_01_000008_create_failed_jobs_table.php
│   │   └── 2026_01_01_000009_create_job_batches_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── AdminUserSeeder.php
├── routes/
│   ├── api.php                             # /api/v1/*
│   ├── console.php
│   └── web.php                             # redirects / → /admin
├── storage/app/public/photos/              # originals + sizes (local disk)
├── tests/
│   ├── Feature/
│   │   ├── Api/V1/
│   │   │   ├── PhotoIndexTest.php
│   │   │   ├── PhotoCrudTest.php
│   │   │   ├── PhotoUploadTest.php
│   │   │   ├── PhotoFavoriteTest.php
│   │   │   ├── BatchUploadTest.php
│   │   │   ├── AlbumCrudTest.php
│   │   │   ├── TagIndexTest.php
│   │   │   └── AuthTest.php
│   │   └── Jobs/ProcessPhotoTest.php
│   └── Unit/
│       └── Services/ImageProcessorTest.php
├── .env.example
├── composer.json
└── phpunit.xml
```

### 3.3 Frontend (React SPA)

```
frontend/
├── public/
│   └── favicon.svg
├── src/
│   ├── api/
│   │   ├── client.ts                       # axios instance, interceptors
│   │   ├── photos.ts
│   │   ├── albums.ts
│   │   ├── tags.ts
│   │   └── auth.ts
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.tsx
│   │   │   ├── Modal.tsx
│   │   │   ├── EmptyState.tsx
│   │   │   ├── Spinner.tsx
│   │   │   └── ConfirmDialog.tsx
│   │   ├── layout/
│   │   │   ├── Navbar.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   └── Shell.tsx                   # wraps Navbar + Sidebar + <Outlet/>
│   │   ├── gallery/
│   │   │   ├── MasonryGrid.tsx
│   │   │   ├── PhotoCard.tsx
│   │   │   ├── PhotoLightbox.tsx
│   │   │   ├── ExifPanel.tsx
│   │   │   └── ProcessingOverlay.tsx
│   │   └── upload/
│   │       ├── UploadModal.tsx
│   │       ├── DropZone.tsx
│   │       └── UploadProgressList.tsx
│   ├── data/
│   │   ├── copy.ts                         # all UI strings
│   │   ├── nav.ts
│   │   └── shortcuts.ts
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useDebounce.ts
│   │   ├── useKeyboardShortcuts.ts
│   │   ├── usePhotos.ts                    # TanStack Query wrappers
│   │   ├── useAlbums.ts
│   │   ├── useTags.ts
│   │   ├── useUpload.ts
│   │   └── useProcessingPoll.ts
│   ├── pages/
│   │   ├── GalleryPage.tsx
│   │   ├── AlbumPage.tsx
│   │   ├── FavoritesPage.tsx
│   │   ├── LoginPage.tsx
│   │   └── NotFoundPage.tsx
│   ├── lib/
│   │   ├── queryClient.ts
│   │   ├── formatBytes.ts
│   │   └── shortcuts.ts
│   ├── types/
│   │   ├── photo.ts
│   │   ├── album.ts
│   │   └── tag.ts
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css                           # @import "tailwindcss"; @theme { ... }
├── tests/
│   ├── hooks/useDebounce.test.ts
│   ├── hooks/useKeyboardShortcuts.test.ts
│   └── components/UploadModal.test.tsx
├── e2e/
│   ├── upload.spec.ts
│   ├── gallery.spec.ts
│   └── lightbox.spec.ts
├── index.html
├── package.json
├── tsconfig.json
├── vite.config.ts                          # plugin: react, tailwindcss; proxy /api
└── playwright.config.ts
```

---

## 4. Database Schema

**Conventions used in every table below:**
- All primary keys are UUIDs (via Laravel's `HasUuids` trait), stored as `CHAR(36)`, **NOT NULL**, no default (generated at model boot).
- All `TIMESTAMP` columns use MySQL 5.7+/MariaDB 10.2+ with microsecond precision (`TIMESTAMP(0)` is acceptable; Laravel uses `timestamp` by default).
- `created_at` and `updated_at` are written by Laravel's `Model::timestamps()`. Declared **NULLABLE** (Laravel's historical default via `$table->timestamps()`) with **no DB default** — Eloquent populates them on INSERT and on UPDATE respectively. Applications MUST NOT rely on DB-side defaults for these columns.
- Every foreign key declares both `ON DELETE` and `ON UPDATE` explicitly. `ON UPDATE` is always `CASCADE` (UUIDs are immutable in practice, but we set it for defensive completeness; DB engine handles any rare mutation).
- Every FK column has an index. Laravel's `foreignUuid(...)->constrained(...)` creates the index automatically; it is listed explicitly in each "Indexes" subsection for clarity.

**Migration-order note:** `albums.cover_photo_id` references `photos.id` and `photos.album_id` references `albums.id` — a circular dependency. Resolve by:
1. Create `albums` **without** the `cover_photo_id` FK.
2. Create `photos` with the `album_id` FK.
3. Run a separate migration that `ALTER TABLE albums ADD CONSTRAINT ...` to add the `cover_photo_id` FK.

Migration filenames already reflect this ordering (§3.2).

---

### 4.1 `users`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| name | VARCHAR(255) | NOT NULL | — | — |
| email | VARCHAR(255) | NOT NULL | — | UNIQUE (`users_email_unique`) |
| email_verified_at | TIMESTAMP | NULL | NULL | — |
| password | VARCHAR(255) | NOT NULL | — | — (bcrypt hash, 60 chars) |
| remember_token | VARCHAR(100) | NULL | NULL | — |
| created_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |
| updated_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |

**Indexes:** PRIMARY (`id`), UNIQUE (`email`).

---

### 4.2 `albums`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| name | VARCHAR(255) | NOT NULL | — | UNIQUE (`albums_name_unique`) |
| description | TEXT | NULL | NULL | — |
| cover_photo_id | CHAR(36) | NULL | NULL | FK → `photos(id)` · ON DELETE **SET NULL** · ON UPDATE **CASCADE** |
| created_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |
| updated_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |

**Indexes:** PRIMARY (`id`), UNIQUE (`name`), INDEX (`cover_photo_id`).

**Rationale for `SET NULL` on `cover_photo_id`:** deleting the photo used as a cover must not cascade-delete the album. Admin/user can pick a new cover.

---

### 4.3 `tags`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| name | VARCHAR(100) | NOT NULL | — | UNIQUE (`tags_name_unique`) |
| slug | VARCHAR(120) | NOT NULL | — | UNIQUE (`tags_slug_unique`) |
| created_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |
| updated_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |

**Indexes:** PRIMARY (`id`), UNIQUE (`name`), UNIQUE (`slug`).

Slug is auto-generated from `name` via the `Tag` model's `booted()` hook using `Str::slug($tag->name)`. Regenerated when `name` changes. Uniqueness collisions resolved by appending a numeric suffix (`-2`, `-3`, …).

---

### 4.4 `photos`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| title | VARCHAR(255) | NOT NULL | — | — |
| description | TEXT | NULL | NULL | — |
| filename | VARCHAR(255) | NOT NULL | — | — (original user-supplied filename) |
| original_path | VARCHAR(500) | NOT NULL | — | — (disk-relative path) |
| thumbnail_path | VARCHAR(500) | NULL | NULL | — (populated by `ProcessPhoto` job) |
| medium_path | VARCHAR(500) | NULL | NULL | — (populated by `ProcessPhoto` job) |
| large_path | VARCHAR(500) | NULL | NULL | — (populated by `ProcessPhoto` job) |
| album_id | CHAR(36) | NULL | NULL | FK → `albums(id)` · ON DELETE **SET NULL** · ON UPDATE **CASCADE** |
| width | INT UNSIGNED | NULL | NULL | — (filled after processing) |
| height | INT UNSIGNED | NULL | NULL | — (filled after processing) |
| file_size | BIGINT UNSIGNED | NOT NULL | — | — (bytes) |
| mime_type | VARCHAR(100) | NOT NULL | — | — |
| is_favorite | TINYINT(1) | NOT NULL | `0` | — (BOOLEAN in Laravel, stored as TINYINT in MySQL) |
| exif | JSON | NULL | NULL | — |
| processing_status | ENUM('pending','processing','completed','failed') | NOT NULL | `'pending'` | — |
| processing_attempts | TINYINT UNSIGNED | NOT NULL | `0` | — |
| processing_error | TEXT | NULL | NULL | — (exception message on failure) |
| created_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |
| updated_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |

**Indexes:**
- PRIMARY (`id`)
- INDEX `photos_album_id_idx` (`album_id`)
- INDEX `photos_is_favorite_idx` (`is_favorite`)
- INDEX `photos_processing_status_idx` (`processing_status`)
- INDEX `photos_created_at_idx` (`created_at`) — used by default "newest" sort
- INDEX `photos_title_idx` (`title`) — supports prefix search on `LIKE 'query%'`

**Rationale for `SET NULL` on `album_id`:** deleting an album preserves the photos; they become unassigned.

---

### 4.5 `photo_tag` (pivot)

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| photo_id | CHAR(36) | NOT NULL | — | FK → `photos(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| tag_id | CHAR(36) | NOT NULL | — | FK → `tags(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| created_at | TIMESTAMP | NOT NULL | `CURRENT_TIMESTAMP` | — (when tag was attached) |

**Keys / Indexes:**
- **Composite PRIMARY KEY** (`photo_id`, `tag_id`)
- INDEX (`tag_id`) — supports reverse-direction lookups ("all photos with tag X")

**Notes:**
- No `id` column, no `updated_at` — this is an attach/detach pivot, not an entity.
- Migration declares it with `$table->primary(['photo_id', 'tag_id'])` after the two FK declarations.
- CASCADE on both sides: deleting a photo removes its tag assignments; deleting a tag removes it from all photos. Photos and tags themselves survive the opposite deletion.

---

### 4.6 `personal_access_tokens` (Sanctum)

Published by `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`. Unmodified from Laravel 13 / Sanctum 4 defaults:

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NOT NULL | auto-increment | PRIMARY KEY |
| tokenable_type | VARCHAR(255) | NOT NULL | — | — (morphs — here always `App\Models\User`) |
| tokenable_id | CHAR(36) | NOT NULL | — | — (User UUID — **Sanctum migration must be adjusted to CHAR(36)** to match `users.id`) |
| name | VARCHAR(255) | NOT NULL | — | — |
| token | VARCHAR(64) | NOT NULL | — | UNIQUE (`personal_access_tokens_token_unique`) |
| abilities | TEXT | NULL | NULL | — |
| last_used_at | TIMESTAMP | NULL | NULL | — |
| expires_at | TIMESTAMP | NULL | NULL | — |
| created_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |
| updated_at | TIMESTAMP | NULL | NULL | — (Laravel-managed) |

**Indexes:** PRIMARY (`id`), UNIQUE (`token`), INDEX (`tokenable_type`, `tokenable_id`).

**Important:** Sanctum's default migration declares `tokenable_id` as `UNSIGNED BIGINT`. Because `users.id` is a UUID (`CHAR(36)`), the stub migration **must** be edited (or replaced) so `tokenable_id` is `CHAR(36)`. No FK constraint is added (polymorphic relationship).

---

### 4.7 `jobs` and `failed_jobs` (Laravel queue)

Published by `php artisan queue:table` and `php artisan queue:failed-table`. Unmodified defaults:

**`jobs`:**

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NOT NULL | auto-increment | PRIMARY KEY |
| queue | VARCHAR(255) | NOT NULL | — | INDEX (`jobs_queue_index`) |
| payload | LONGTEXT | NOT NULL | — | — |
| attempts | TINYINT UNSIGNED | NOT NULL | — | — |
| reserved_at | INT UNSIGNED | NULL | NULL | — (unix timestamp) |
| available_at | INT UNSIGNED | NOT NULL | — | — (unix timestamp) |
| created_at | INT UNSIGNED | NOT NULL | — | — (unix timestamp) |

**`failed_jobs`:**

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NOT NULL | auto-increment | PRIMARY KEY |
| uuid | CHAR(36) | NOT NULL | — | UNIQUE (`failed_jobs_uuid_unique`) |
| connection | TEXT | NOT NULL | — | — |
| queue | TEXT | NOT NULL | — | — |
| payload | LONGTEXT | NOT NULL | — | — |
| exception | LONGTEXT | NOT NULL | — | — |
| failed_at | TIMESTAMP | NOT NULL | `CURRENT_TIMESTAMP` | — |

Both tables are read by the Filament `QueueMonitor` widget (§7.4). In production with `QUEUE_CONNECTION=sqs`, only `failed_jobs` is used (pending jobs live in SQS).

---

### 4.8 `job_batches` (Laravel batching)

Published by `php artisan queue:batches-table`. Required for `POST /photos/batch` (§6.2). Unmodified defaults:

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | VARCHAR(255) | NOT NULL | — | PRIMARY KEY (UUID generated by Laravel `Bus::batch()`) |
| name | VARCHAR(255) | NOT NULL | — | — |
| total_jobs | INT | NOT NULL | — | — |
| pending_jobs | INT | NOT NULL | — | — |
| failed_jobs | INT | NOT NULL | — | — |
| failed_job_ids | LONGTEXT | NOT NULL | — | — (JSON array) |
| options | MEDIUMTEXT | NULL | NULL | — |
| cancelled_at | INT | NULL | NULL | — (unix timestamp) |
| created_at | INT | NOT NULL | — | — (unix timestamp) |
| finished_at | INT | NULL | NULL | — (unix timestamp) |

---

## 5. Eloquent Relationships

```php
// Photo
public function album(): BelongsTo           // → Album
public function tags(): BelongsToMany        // → Tag (photo_tag pivot)
public function coverOf(): HasOne            // inverse of Album.coverPhoto

// Album
public function photos(): HasMany            // → Photo (via album_id)
public function coverPhoto(): BelongsTo      // → Photo (via cover_photo_id)

// Tag
public function photos(): BelongsToMany      // → Photo

// User
public function tokens(): MorphMany          // Sanctum HasApiTokens trait
```

**Cascade rules** (DB-enforced via FKs in §4; Laravel-side observers only handle side effects):
- Deleting a `Photo` → `photo_tag` rows removed (CASCADE); `albums.cover_photo_id` set to NULL (SET NULL); `PhotoObserver::deleted()` deletes all 4 files from the `photos` disk.
- Deleting an `Album` → `photos.album_id` set to NULL (SET NULL); photos survive and become unassigned.
- Deleting a `Tag` → `photo_tag` rows removed (CASCADE); photos survive.
- Deleting a `User` → personal access tokens cascade via Sanctum's morph; no other data is user-scoped in v1.

---

## 6. API Endpoints

### 6.0 Conventions

- **Base URL:** `/api/v1`. Version is a path segment — a `v2` may coexist later.
- **Content types:** requests are either `application/json` or, for file uploads, `multipart/form-data`. Responses are always `application/json; charset=utf-8`.
- **Auth header:** protected routes require `Authorization: Bearer <sanctum-token>`. Missing/expired token → `401 unauthenticated`. GET routes are public unless noted.
- **Rate limits (per IP):** `/auth/*` is `throttle:10,1` (10 req/min); all other routes are `throttle:120,1` (120 req/min). Exceeding → `429 rate_limited`.
- **Error envelope:** all non-2xx responses use the shape defined in §10.1. Each endpoint below lists only its **additional** possible status codes beyond the universal ones (`401`, `429`, `500`).
- **Pagination envelope** (applied to every list endpoint):
  ```json
  {
    "data": [ /* Resource[] */ ],
    "links": {
      "first": "https://api.example.com/api/v1/photos?page=1",
      "last":  "https://api.example.com/api/v1/photos?page=12",
      "prev":  null,
      "next":  "https://api.example.com/api/v1/photos?page=2"
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 12,
      "path": "https://api.example.com/api/v1/photos",
      "per_page": 24,
      "to": 24,
      "total": 281
    }
  }
  ```
- **Single-resource envelope:** `{ "data": { /* Resource */ } }`. CRUD returns the resource wrapped in `data`; favorite-toggle and auth endpoints return unwrapped JSON (documented per endpoint).
- **Timestamps:** ISO-8601 UTC (`"2026-04-24T12:00:00Z"`).
- **IDs:** UUID v7 strings unless otherwise stated.

---

### 6.1 Auth

#### `POST /auth/register`
Create a new user and return an API token.

- **Auth:** none
- **Content-Type:** `application/json`
- **Request body:**
  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `name` | string | yes | 2–255 chars |
  | `email` | string | yes | valid email, unique in `users.email` |
  | `password` | string | yes | min 8 chars, confirmed |
  | `password_confirmation` | string | yes | must match `password` |

- **Success `201 Created`** (unwrapped):
  ```json
  {
    "token": "1|aBcDeFgH...",
    "token_type": "Bearer",
    "user": { "id": "uuid", "name": "Alex", "email": "alex@example.com", "created_at": "2026-04-24T12:00:00Z" }
  }
  ```
- **Errors:** `422` (validation).

---

#### `POST /auth/login`
Exchange email+password for a token.

- **Auth:** none
- **Content-Type:** `application/json`
- **Request body:**
  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `email` | string | yes | valid email |
  | `password` | string | yes | — |
  | `device_name` | string | no | default `"web"` — stored in `personal_access_tokens.name` |

- **Success `200 OK`** (unwrapped): same shape as `/auth/register`.
- **Errors:**
  - `422` — missing/invalid fields.
  - `401` — invalid credentials. Body: `{ "message": "The provided credentials are incorrect." }`.

---

#### `POST /auth/logout`
Revoke the current token.

- **Auth:** token
- **Request body:** empty.
- **Success `204 No Content`** (empty body).
- **Errors:** `401`.

---

#### `GET /auth/me`
Return the authenticated user.

- **Auth:** token
- **Success `200 OK`** (wrapped):
  ```json
  { "data": { "id": "uuid", "name": "Alex", "email": "alex@example.com", "email_verified_at": null, "created_at": "2026-04-24T12:00:00Z" } }
  ```
- **Errors:** `401`.

---

### 6.2 Photos

#### `GET /photos`
Paginated list with filters, search, and sorting.

- **Auth:** none
- **Query parameters:**

  | Param | Type | Required | Default | Rules / notes |
  |---|---|---|---|---|
  | `search` | string | no | `null` | 1–100 chars. Matches `title` (prefix, `LIKE 'q%'`) OR `description` (contains, `LIKE '%q%'`). |
  | `tags[]` | string[] (tag slugs) | no | `[]` | Each value 1–120 chars, must exist in `tags.slug`. Up to 10 values. **AND logic** — photo must have every listed tag. |
  | `album_id` | UUID | no | `null` | Must exist in `albums.id` or return `422`. |
  | `favorites` | enum `0`/`1` | no | `0` | When `1`, only photos with `is_favorite = true`. |
  | `sort` | enum | no | `newest` | One of: `newest` (`created_at DESC`), `oldest` (`created_at ASC`), `title_asc` (`title ASC`), `favorites_first` (`is_favorite DESC, created_at DESC`). |
  | `per_page` | integer | no | `24` | Min `1`, max `100`. |
  | `page` | integer | no | `1` | Min `1`. |

- **Success `200 OK`** — pagination envelope where `data` is an array of the **Photo resource** (§6.5). Relations always eager-loaded: `album` (id, name) and `tags` (id, name, slug).
- **Errors:**
  - `422` — invalid query param (unknown `sort`, bad UUID, `per_page` out of range, unknown tag slug).

---

#### `GET /photos/{id}`
Fetch a single photo with full details.

- **Auth:** none
- **Path parameters:** `id` — UUID.
- **Success `200 OK`**: `{ "data": Photo }` — full **Photo resource** (§6.5) including `exif`, `album`, `tags`.
- **Errors:** `404` if no photo with that UUID.

---

#### `POST /photos`
Upload a single photo and queue processing.

- **Auth:** token
- **Content-Type:** `multipart/form-data` **(required — JSON body is rejected with `415`)**
- **Form fields:**

  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `photo` | File | yes | MIME must be `image/jpeg` \| `image/png` \| `image/webp`. Size ≤ 10 MB (10240 kB). Laravel rule: `image\|mimes:jpg,jpeg,png,webp\|max:10240`. |
  | `title` | string | no | 1–255 chars. Defaults to `pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME)`. |
  | `description` | string | no | 0–5000 chars. |
  | `album_id` | UUID | no | Must exist in `albums.id`. |
  | `tags[]` | string[] (tag names) | no | Each 1–100 chars. Tags are **upserted** by name — unknown names create new `tags` rows (slug auto-generated). Up to 20 values. |
  | `is_favorite` | boolean (`0`/`1`/`true`/`false`) | no | Default `false`. |

- **Behavior:** original file saved immediately; `Photo` row created with `processing_status = 'pending'`; `ProcessPhoto` job dispatched; endpoint returns before processing completes.
- **Success `201 Created`**: `{ "data": Photo }` — Photo resource with `processing_status = 'pending'`, `thumbnail_path` / `medium_path` / `large_path` / `width` / `height` / `exif` all `null`.
- **Errors:**
  - `415 unsupported_media_type` — request is not multipart, or file MIME is not in the allowed set.
  - `413 payload_too_large` — file exceeds 10 MB (also surfaced as `422` if within PHP's `upload_max_filesize` but above Laravel's `max:10240` rule).
  - `422 validation_failed` — missing `photo`, invalid `album_id`, bad field types.

---

#### `PATCH /photos/{id}`
Update photo metadata. **Cannot** replace the file — upload a new photo instead.

- **Auth:** token
- **Content-Type:** `application/json`
- **Path parameters:** `id` — UUID.
- **Request body** (all optional, but at least one required):

  | Field | Type | Rules |
  |---|---|---|
  | `title` | string | 1–255 chars |
  | `description` | string\|null | 0–5000 chars, `null` clears it |
  | `album_id` | UUID\|null | Must exist; `null` unassigns from album |
  | `tags[]` | string[] (tag names) | Full replacement (not additive); empty array clears all tags. Same upsert rules as `POST /photos`. |
  | `is_favorite` | boolean | — |

- **Success `200 OK`**: `{ "data": Photo }` — full Photo resource with updated fields.
- **Errors:** `404` (unknown id), `422` (validation).

---

#### `DELETE /photos/{id}`
Delete photo row and all 4 files from the `photos` disk.

- **Auth:** token
- **Path parameters:** `id` — UUID.
- **Success `204 No Content`** (empty body).
- **Errors:** `404`.

---

#### `POST /photos/{id}/favorite`
Toggle `is_favorite`.

- **Auth:** token
- **Request body:** empty.
- **Path parameters:** `id` — UUID.
- **Success `200 OK`** (unwrapped):
  ```json
  { "id": "uuid", "is_favorite": true }
  ```
- **Errors:** `404`.

---

#### `POST /photos/batch`
Upload 2–20 photos as a batch. Creates a Laravel `Bus::batch` of `ProcessPhoto` jobs.

- **Auth:** token
- **Content-Type:** `multipart/form-data` **(required)**
- **Form fields:**

  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `files[]` | File[] | yes | 2–20 items. Each item: same MIME/size rules as `POST /photos` `photo` field. Laravel rules: `files\|array\|min:2\|max:20`, `files.*\|image\|mimes:jpg,jpeg,png,webp\|max:10240`. |
  | `album_id` | UUID | no | Must exist. Applied to every uploaded photo. |
  | `tags[]` | string[] (tag names) | no | Up to 20. Applied to every uploaded photo. |

- **Behavior:** every file → `Photo` row with `processing_status = 'pending'`; one `ProcessPhoto` job per photo is added to a `Bus::batch`; batch ID returned for progress polling.
- **Success `202 Accepted`** (unwrapped):
  ```json
  {
    "batch_id": "uuid",
    "total": 5,
    "photo_ids": ["uuid1", "uuid2", "uuid3", "uuid4", "uuid5"]
  }
  ```
- **Errors:**
  - `415` — not multipart, or any file MIME rejected.
  - `413` — any file exceeds 10 MB.
  - `422` — fewer than 2 or more than 20 files, invalid `album_id`, etc.

---

#### `GET /photos/batch/{batchId}`
Poll batch progress.

- **Auth:** token
- **Path parameters:** `batchId` — UUID returned by `POST /photos/batch`.
- **Success `200 OK`** (unwrapped):
  ```json
  {
    "batch_id": "uuid",
    "total": 5,
    "pending": 2,
    "processed": 3,
    "failed": 0,
    "finished": false,
    "cancelled": false,
    "created_at": "2026-04-24T12:00:00Z",
    "finished_at": null
  }
  ```
  When `finished === true`, polling should stop.
- **Errors:** `404` — batch ID not found (or already pruned by `queue:prune-batches`).

---

### 6.3 Albums

#### `GET /albums`
List every album with its photo count.

- **Auth:** none
- **Query parameters:**
  | Param | Type | Required | Default | Rules |
  |---|---|---|---|---|
  | `sort` | enum | no | `name_asc` | One of `name_asc`, `newest`, `most_photos`. |
  | `per_page` | int | no | `50` | Max `100`. |
  | `page` | int | no | `1` | — |

- **Success `200 OK`** — pagination envelope, `data` is an array of **Album resource** (§6.5).
- **Errors:** `422`.

---

#### `GET /albums/{id}`
Fetch a single album.

- **Auth:** none
- **Path parameters:** `id` — UUID.
- **Success `200 OK`**: `{ "data": Album }` with `cover_photo` eager-loaded.
- **Errors:** `404`.

---

#### `POST /albums`
Create an album.

- **Auth:** token
- **Content-Type:** `application/json`
- **Request body:**
  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `name` | string | yes | 1–255 chars, unique in `albums.name` |
  | `description` | string | no | 0–5000 chars |
  | `cover_photo_id` | UUID | no | Must exist in `photos.id` |

- **Success `201 Created`**: `{ "data": Album }`.
- **Errors:** `422` (validation, including duplicate name).

---

#### `PATCH /albums/{id}`
Update album fields.

- **Auth:** token
- **Content-Type:** `application/json`
- **Request body:** any subset of `name`, `description`, `cover_photo_id` — same rules as `POST /albums`; `name` must be unique ignoring the current record.
- **Success `200 OK`**: `{ "data": Album }`.
- **Errors:** `404`, `422`.

---

#### `DELETE /albums/{id}`
Delete album. Photos are NOT deleted — their `album_id` is set to `NULL` (see §4 ON DELETE SET NULL).

- **Auth:** token
- **Success `204 No Content`**.
- **Errors:** `404`.

---

### 6.4 Tags

#### `GET /tags`
List every tag with its photo count. **Not paginated** (tag set is small).

- **Auth:** none
- **Query parameters:**
  | Param | Type | Required | Default | Rules |
  |---|---|---|---|---|
  | `sort` | enum | no | `count_desc` | One of `count_desc`, `name_asc`. |

- **Success `200 OK`** (unwrapped):
  ```json
  { "data": [ { "id": "uuid", "name": "sunset", "slug": "sunset", "photos_count": 42 } ] }
  ```
- **Errors:** `422`.

**Note:** tag creation is implicit via `POST /photos` / `PATCH /photos/{id}` / `POST /photos/batch`. There is no `POST /tags` in v1 — tags are managed through photo operations (and via the Filament admin panel).

---

### 6.5 Resource shapes

**Photo resource** — returned by every `/photos/*` endpoint (except favorite-toggle and batch):
```json
{
  "id": "uuid",
  "title": "Sunset",
  "description": "...",
  "filename": "IMG_1234.jpg",
  "urls": {
    "original":  "https://cdn.example.com/photos/originals/abc.jpg",
    "thumbnail": "https://cdn.example.com/photos/thumbnails/abc.jpg",
    "medium":    "https://cdn.example.com/photos/medium/abc.jpg",
    "large":     "https://cdn.example.com/photos/large/abc.jpg"
  },
  "width": 4000,
  "height": 3000,
  "file_size": 2500000,
  "mime_type": "image/jpeg",
  "is_favorite": true,
  "exif": {
    "camera": "Canon EOS R5",
    "iso": 400,
    "aperture": "f/2.8",
    "shutter": "1/250",
    "focal_length": "85mm",
    "taken_at": "2026-02-14T18:32:00Z"
  },
  "processing_status": "completed",
  "processing_error": null,
  "album": { "id": "uuid", "name": "Travel" },
  "tags": [ { "id": "uuid", "name": "sunset", "slug": "sunset" } ],
  "created_at": "2026-04-24T12:00:00Z",
  "updated_at": "2026-04-24T12:01:00Z"
}
```

Field nullability in the Photo resource:
- `description`, `exif`, `processing_error`, `album` — `null` when unset.
- `urls.thumbnail`, `urls.medium`, `urls.large`, `width`, `height` — `null` until `processing_status === 'completed'`. `urls.original` is always populated.
- `tags` — always an array (possibly empty), never `null`.

**Album resource** — returned by every `/albums/*` endpoint:
```json
{
  "id": "uuid",
  "name": "Travel",
  "description": "Trips from 2024–2026",
  "cover_photo": {
    "id": "uuid",
    "urls": { "thumbnail": "https://..." }
  },
  "photos_count": 42,
  "created_at": "2026-04-24T12:00:00Z",
  "updated_at": "2026-04-24T12:00:00Z"
}
```
`cover_photo` is `null` if `cover_photo_id` is null or refers to a deleted photo.

**Tag resource** — returned by `GET /tags`:
```json
{
  "id": "uuid",
  "name": "sunset",
  "slug": "sunset",
  "photos_count": 42
}
```

---

### 6.6 Error responses (per-endpoint summary)

All error bodies follow the envelope in §10.1. The table below lists the **endpoint-specific** status codes. Every endpoint additionally may return `401` (when auth is required and missing/invalid), `429` (rate limited), or `500` (server error).

| Endpoint | Possible status codes beyond the universal set |
|---|---|
| `POST /auth/register` | `201`, `422` |
| `POST /auth/login` | `200`, `401` (bad creds), `422` |
| `POST /auth/logout` | `204` |
| `GET /auth/me` | `200` |
| `GET /photos` | `200`, `422` |
| `GET /photos/{id}` | `200`, `404` |
| `POST /photos` | `201`, `413`, `415`, `422` |
| `PATCH /photos/{id}` | `200`, `404`, `422` |
| `DELETE /photos/{id}` | `204`, `404` |
| `POST /photos/{id}/favorite` | `200`, `404` |
| `POST /photos/batch` | `202`, `413`, `415`, `422` |
| `GET /photos/batch/{batchId}` | `200`, `404` |
| `GET /albums` | `200`, `422` |
| `GET /albums/{id}` | `200`, `404` |
| `POST /albums` | `201`, `422` |
| `PATCH /albums/{id}` | `200`, `404`, `422` |
| `DELETE /albums/{id}` | `204`, `404` |
| `GET /tags` | `200`, `422` |

---

## 7. Filament Admin Specification

Panel mounted at `/admin`. Brand: `PhotoGallery Pro Admin`. Primary color: `#6366F1` (indigo-500).

### 7.1 PhotoResource
**Table columns:**
- `thumbnail_url` — `ImageColumn`, 60×60 rounded, falls back to placeholder if `thumbnail_path` null.
- `title` — `TextColumn`, searchable, sortable.
- `album.name` — `TextColumn`, sortable.
- `tags.name` — `TextColumn` with `.badge()` + `.separator(',')`.
- `file_size` — `TextColumn` formatted as human bytes.
- `is_favorite` — `ToggleColumn` (inline toggle).
- `processing_status` — `TextColumn` as `.badge()` with colors: pending=gray, processing=blue, completed=green, failed=red.
- `created_at` — `TextColumn`, since format, sortable.

**Filters:**
- `album_id` — `SelectFilter` (all albums).
- `tags` — `SelectFilter::multiple()` relationship.
- `is_favorite` — `TernaryFilter`.
- `created_at` — `Filter` with date range (from/until).
- `processing_status` — `SelectFilter` with enum options.

**Header actions:** `CreateAction`.

**Row actions:** `ViewAction`, `EditAction`, `DeleteAction`, custom `ReprocessAction` (dispatches `ProcessPhoto` job).

**Bulk actions:** `DeleteBulkAction`, `AssignToAlbumBulkAction` (select modal), `ToggleFavoriteBulkAction`, `ReprocessBulkAction`.

**Form:**
- `FileUpload::make('original_path')` — disk `photos`, directory `originals`, accepted `image/jpeg,image/png,image/webp`, max 10 MB, drag-drop enabled, shows image preview. On save triggers `ProcessPhoto` if new upload.
- `TextInput::make('title')` — required, max 255.
- `Textarea::make('description')` — rows 3.
- `Select::make('album_id')` — relationship `album`, searchable, preload, createOptionForm.
- `Select::make('tags')` — relationship multiple, searchable, preload, `createOptionForm` with inline name → auto-slug.
- `Toggle::make('is_favorite')`.

### 7.2 AlbumResource
**Table columns:** `cover_photo.thumbnail_url` (ImageColumn), `name` (searchable, sortable), `photos_count` (`counts('photos')`), `created_at`.

**Form:** `TextInput::make('name')` unique (ignoreRecord) required; `Textarea::make('description')`; `Select::make('cover_photo_id')` scoped to this album's photos.

**Relation managers:** `PhotosRelationManager` — photos table with attach/detach action, re-use PhotoResource columns.

### 7.3 TagResource
**Table columns:** `name` (searchable, sortable), `slug` (read-only), `photos_count`.

**Form:** `TextInput::make('name')` unique required — `slug` auto-filled via `afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))`. `TextInput::make('slug')` unique, prefix icon lock.

### 7.4 Dashboard widgets

**StatsOverview** (`Widgets\StatsOverviewWidget`): 4 stats cards — Total Photos, Total Albums, Total Tags, Total Storage (sum of `file_size`, human-formatted).

**RecentUploadsTable** (`Widgets\TableWidget`): last 10 photos ordered by `created_at DESC`; columns thumbnail, title, album, status, created_at; row action `ViewAction` linking to PhotoResource.

**QueueMonitor** (`Widgets\StatsOverviewWidget`): 3 stats — Pending Jobs (`jobs` table count), Failed Jobs (`failed_jobs` count with red color when > 0), Photos Processing (where `processing_status = 'processing'`).

### 7.5 Storage Management page
Custom page `App\Filament\Pages\StorageManagement`, navigation group "System":
- Per-disk breakdown: originals / thumbnails / medium / large — size + file count.
- Total storage used.
- Button: **Regenerate all thumbnails** — dispatches `ProcessPhoto` for every photo.
- Button: **Purge failed** — removes `failed_jobs` rows.

---

## 8. React Frontend Component Specification

### 8.1 App shell + routing
`App.tsx` wires `QueryClientProvider` + `BrowserRouter`. Routes:
- `/` → `GalleryPage` (all photos)
- `/favorites` → `FavoritesPage`
- `/albums/:albumId` → `AlbumPage`
- `/login` → `LoginPage`
- `*` → `NotFoundPage`

All authenticated routes render inside `<Shell>` which renders Navbar + Sidebar + `<Outlet/>`.

### 8.2 `Navbar`
- Left: app logo + title.
- Center: `SearchInput` (controlled), debounced 300ms via `useDebounce`, writes to URL `?search=`.
- Right: `SortDropdown` (newest / oldest / A-Z / favorites-first), `UploadButton` (opens `UploadModal`), auth menu (login/logout).

### 8.3 `Sidebar`
- Sections:
  - "All Photos" link → `/`.
  - "Favorites" link → `/favorites` with heart icon + count badge.
  - "Albums" — list from `useAlbums()`, each with `photos_count`, link to `/albums/:id`.
  - "Tags" — list from `useTags()`, sorted by count, clickable chips that toggle `?tags[]=` in URL (multi-select).

### 8.4 `MasonryGrid`
- CSS columns approach (`columns-2 md:columns-3 lg:columns-4 gap-4`).
- Each child `PhotoCard` uses `break-inside-avoid`.
- Infinite scroll via `useInfiniteQuery` — next page fetched when sentinel enters viewport (`IntersectionObserver`).
- Empty state: `<EmptyState>` with copy from `data/copy.ts` and CTA "Upload your first photo".

### 8.5 `PhotoCard`
- `<img loading="lazy" src={urls.thumbnail} />` — `aspect-ratio` from width/height to prevent layout shift.
- Hover overlay (desktop) / always visible on mobile: title + heart icon (favorite toggle).
- Click opens `PhotoLightbox` — sets `?photo=:id` in URL so lightbox is linkable.
- Processing overlay: if `processing_status === 'pending' | 'processing'`, show `ProcessingOverlay` with spinner. If `failed`, show red error icon with tooltip.

### 8.6 `PhotoLightbox`
- Portal to `<body>`, backdrop `bg-black/90`.
- Displays `urls.large` (falls back to `original` during processing).
- Left/right arrows (on-screen + keyboard) cycle through current filtered list (uses same query data as grid).
- Bottom bar: title (inline editable), description (inline editable textarea), tags (chip multi-select with create), "Delete" (confirm), "View full size" (opens `original` in new tab).
- Right drawer: `ExifPanel` — key/value list from `exif` JSON, empty state "No EXIF data available".
- Closes on backdrop click, Escape key, or `?photo=` cleared from URL.

### 8.7 `UploadModal` + `DropZone`
- Drag-and-drop zone using native HTML5 drag events (no dropzone lib).
- File input fallback for click-to-browse.
- Client validation: MIME in {`image/jpeg`,`image/png`,`image/webp`}, size ≤ 10 MB.
- Queue of files with `UploadProgressList`: each row shows filename, progress bar (axios `onUploadProgress`), status icon (pending/uploading/done/error).
- Single-file uploads hit `POST /photos`; 2+ files hit `POST /photos/batch` (up to 20) and then poll `GET /photos/batch/:id` every 1s until complete.
- After upload, start `useProcessingPoll` for new photo IDs — polls `GET /photos/:id` every 3s until `processing_status === 'completed' | 'failed'`, then invalidates gallery query.

### 8.8 Keyboard shortcuts (`useKeyboardShortcuts`)
Registered globally on `window`. Ignored when focus is in an `<input>`, `<textarea>`, or `[contenteditable]` — except `/` which *steals* focus.
| Key | Action |
|---|---|
| `←` | Previous photo (lightbox open) |
| `→` | Next photo (lightbox open) |
| `Escape` | Close lightbox/modal |
| `F` | Toggle favorite on current photo |
| `Delete` | Delete current photo (with confirm) |
| `/` | Focus search input |

### 8.9 Data module (`src/data/`)
All user-facing strings live here. Example `copy.ts`:
```ts
export const copy = {
  gallery: {
    emptyTitle: "No photos yet",
    emptyBody: "Upload your first photo to get started.",
    emptyCta: "Upload photos",
  },
  upload: {
    title: "Upload photos",
    dropHere: "Drop files here or click to browse",
    maxSize: "Max 10 MB per file — JPG, PNG, or WebP",
    maxBatch: "Up to 20 files at once",
  },
  // ...
} as const;
```

### 8.10 API client (`src/api/client.ts`)
Axios instance with `baseURL: '/api/v1'`. Request interceptor attaches `Authorization: Bearer <token>` from `localStorage`. Response interceptor: on 401 → clear token + redirect to `/login`; on 413 → toast "File too large"; on 422 → surface `errors` to caller; on 5xx → toast "Server error, please retry".

### 8.11 Vite config
```ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/storage': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
});
```

### 8.12 `src/index.css`
```css
@import "tailwindcss";

@theme {
  --color-brand-500: oklch(0.65 0.2 270);
  --font-sans: "Inter", system-ui, sans-serif;
}
```
No `tailwind.config.js`, no `postcss.config.js`, no `autoprefixer`.

---

## 9. Authentication

### 9.1 Filament (admin)
- Standard Laravel session auth via `web` guard.
- `AdminPanelProvider::authGuard('web')`.
- Seeded via `AdminUserSeeder` (email+password from `.env`).

### 9.2 React SPA (Sanctum **token mode**, not cookie)
- `config/sanctum.php` — token expiration 30 days.
- `POST /auth/login` returns `{ token: string, user: User }`.
- Frontend stores token in `localStorage` under `pgp_token`.
- Axios attaches `Authorization: Bearer <token>` header.
- Route protection: `Route::middleware('auth:sanctum')->group(...)` wraps all mutating endpoints.
- Public GETs remain unauthenticated.
- `POST /auth/logout` revokes `$request->user()->currentAccessToken()`.

### 9.3 CORS
`config/cors.php` — `paths: ['api/*']`, `allowed_origins` from `FRONTEND_URL` env, `supports_credentials: false` (token mode).

---

## 10. Error Handling

### 10.1 API error envelopes

All API errors share a consistent shape, emitted via `bootstrap/app.php`'s exception handler:

| HTTP | Code | Shape |
|---|---|---|
| 401 | `unauthenticated` | `{ "message": "Unauthenticated." }` |
| 403 | `forbidden` | `{ "message": "..." }` |
| 404 | `not_found` | `{ "message": "Resource not found." }` |
| 413 | `payload_too_large` | `{ "message": "File exceeds 10 MB limit." }` (also enforced via FormRequest) |
| 415 | `unsupported_media_type` | `{ "message": "Only JPG, PNG, WebP supported." }` |
| 422 | `validation_failed` | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 429 | `rate_limited` | `{ "message": "Too many requests." }` |
| 500 | `server_error` | `{ "message": "Server error." }` (details only in `APP_DEBUG=true`) |

Force-JSON middleware on `/api/*` ensures no HTML error pages leak.

### 10.2 Frontend error UX
- **Toasts (sonner):** success (green), error (red), info (blue). Triggered by mutation hooks on success/failure.
- **Inline validation:** 422 errors surface under the corresponding input via React Hook Form `setError`.
- **Empty states:** `<EmptyState>` component with icon, heading, body, optional CTA.
- **Network errors:** toast + retry button in query error boundary.
- **Processing-failed photos:** red badge overlay on `PhotoCard` + "Retry" action in lightbox.

---

## 11. Image Processing Pipeline

### 11.1 Upload flow
1. `POST /photos` (or `POST /photos/batch`) receives multipart file.
2. `StorePhotoRequest` validates: `image|mimes:jpg,jpeg,png,webp|max:10240` (10 MB in kB).
3. Controller stores original via `Storage::disk('photos')->putFileAs('originals', $file, $uuid.'.'.$ext)`.
4. Photo row created with `processing_status = 'pending'`, file metadata (size, mime, original filename).
5. `ProcessPhoto::dispatch($photo)` queued.
6. Controller returns `201 Created` with the `Photo` resource — client does NOT wait for processing.

### 11.2 `ProcessPhoto` job
- `implements ShouldQueue`, `$tries = 3`, `$backoff = [10, 30, 60]` seconds.
- `handle()` flow:
  1. Set `processing_status = 'processing'`, save.
  2. Load original from `Storage::disk('photos')`.
  3. Use `ImageProcessor` service → Intervention Image v3 to generate 3 sizes:

| Size | Width | Height | Quality | Format |
|---|---|---|---|---|
| thumbnail | 300 px | auto (aspect) | 80 | JPEG |
| medium | 800 px | auto | 85 | JPEG |
| large | 1600 px | auto | 90 | JPEG |

  Never upscale — if source < target, skip and store source as that size.
  4. Write outputs to `photos/thumbnails/`, `photos/medium/`, `photos/large/`.
  5. Extract EXIF via `ExifExtractor` — camera model, ISO, aperture, shutter, focal length, taken_at. Store in `exif` JSON.
  6. Update `width`, `height` from source, store all paths.
  7. Set `processing_status = 'completed'`, save.
- `failed()` callback: `processing_status = 'failed'`, persist `processing_error` (exception message).

### 11.3 Batch processing
`POST /photos/batch` creates a Laravel `Bus::batch([...])` of `ProcessPhoto` jobs. Returns `{ batch_id, total }`. Clients poll `GET /photos/batch/:id` which returns Laravel's `Batch::toArray()` projected into `{ total, processed, failed, finished: bool }`.

### 11.4 Env switching (same code, different disks/queues)

**`.env` (local):**
```
QUEUE_CONNECTION=database
FILESYSTEM_DISK=photos
PHOTOS_DISK=public
```

**`.env` (production):**
```
QUEUE_CONNECTION=sqs
SQS_KEY=...
SQS_SECRET=...
SQS_PREFIX=https://sqs.eu-west-1.amazonaws.com/...
SQS_QUEUE=photogallery-pro
SQS_REGION=eu-west-1

FILESYSTEM_DISK=photos
PHOTOS_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=photogallery-pro-prod
AWS_URL=https://cdn.example.com
```

**`config/filesystems.php` addition:**
```php
'disks' => [
    // ...
    'photos' => [
        'driver' => env('PHOTOS_DISK', 'public') === 's3' ? 's3' : 'local',
        'root' => storage_path('app/public/photos'),
        'url' => env('APP_URL').'/storage/photos',
        'visibility' => 'public',
        // s3 driver inherits AWS_* env vars when PHOTOS_DISK=s3
    ],
],
```

All file operations use `Storage::disk('photos')` exclusively — no direct filesystem calls.

### 11.5 Workers
- **Local:** `php artisan queue:work --queue=default --tries=3 --timeout=120`
- **Production:** same command, run under Supervisor; horizontally scalable because SQS is shared.

---

## 12. Testing Plan Checklist

### 12.1 Backend (Pest)
- [ ] `PhotoIndexTest` — search filter, `tags[]` AND logic, album filter, favorites filter, each sort mode, pagination metadata.
- [ ] `PhotoCrudTest` — show returns tags+album, update patches metadata, delete removes files + row, 404 on unknown ID.
- [ ] `PhotoUploadTest` — 422 on missing/invalid MIME, 413 on >10 MB, 201 returns photo with `processing_status=pending`, `ProcessPhoto` queued (`Queue::fake()`).
- [ ] `PhotoFavoriteTest` — toggles flag both directions; auth required.
- [ ] `BatchUploadTest` — rejects >20 files, returns batch_id, `GET /photos/batch/:id` returns progress.
- [ ] `AlbumCrudTest` — unique name validation, photos_count in index, cover set/unset.
- [ ] `TagIndexTest` — returns tags sorted by `photos_count` desc.
- [ ] `AuthTest` — register/login/logout happy paths, login rejects bad creds, `/auth/me` requires token.
- [ ] `AuthProtectionTest` — every mutating endpoint returns 401 without token.
- [ ] `ProcessPhotoTest` (Jobs) — generates 3 sizes with correct dimensions, extracts EXIF, sets status=completed; on throw sets status=failed after 3 attempts.
- [ ] `ImageProcessorTest` (Unit) — never upscales; preserves aspect ratio; outputs JPEG regardless of input format.

### 12.2 Frontend (Vitest)
- [ ] `useDebounce` — emits only after delay, cancels on unmount.
- [ ] `useKeyboardShortcuts` — each shortcut fires handler; ignores when input focused (except `/`).
- [ ] `useUpload` — rejects oversize/bad-MIME before network, reports per-file progress.
- [ ] `UploadModal` — drag-drop adds files, invalid files show inline error, submit disabled during upload.
- [ ] `PhotoLightbox` — arrow keys cycle, Escape closes, Delete confirms.

### 12.3 E2E (Playwright)
- [ ] **Upload → process → appear in grid** — login, upload a fixture JPG, poll until status=completed, reload gallery, assert tile visible.
- [ ] **Search + tag filter** — seed 10 photos with mixed tags, type in search, click tag chip, assert filtered result set.
- [ ] **Lightbox keyboard nav** — open, arrow right cycles through photos, F toggles heart, Escape closes.
- [ ] **Auth guard** — logged-out user can browse but sees login modal when attempting upload.
- [ ] **Batch upload** — drop 5 files, assert batch progress reaches 5/5 and all tiles render.

---

## 13. Claude Code Build Workflow

This section is the **operator runbook** for building PhotoGallery Pro with Claude Code. Each phase is self-contained, ends in a commit, and has a runnable verification step. The prompts below are written to be **copy-pasted directly into Claude Code**, one phase at a time. Every prompt references authoritative SPEC.md sections so Claude doesn't re-invent details — the spec is the source of truth.

**How to use this section:**
1. Open SPEC.md in the repo root (already done).
2. For each phase, paste the prompt inside the fenced "PROMPT" block into Claude Code.
3. When Claude finishes, run the **Verify** block. If it passes, move to the next phase.
4. Do not skip phases — dependencies are strict (e.g. Phase 6 depends on Phase 2 migrations).

**Global prompt preamble** (prepend to every prompt after Phase 1):
> You are working inside this repo. The spec is `SPEC.md` at the repo root — it is authoritative. Read the sections referenced in the prompt and follow them literally. Do NOT invent fields, endpoints, or file paths that aren't in the spec. Run verification before claiming completion. Commit at the end with the message specified in the prompt.

---

### Phase 1 — Backend foundation (models, migrations, seed)
**Goal:** Laravel 13 project scaffolded, UUID primary keys wired, all five application tables migrated, models with relationships, seeded admin user.

**Prompt:**
```
Bootstrap the Laravel backend per SPEC.md §3.2 and §4.

1. From repo root, scaffold the Laravel app in backend/:
     composer create-project laravel/laravel backend "^13.0"
2. cd backend; install dependencies:
     composer require filament/filament "^5.0" \
                      intervention/image-laravel "^3.0" \
                      laravel/sanctum "^4.0" \
                      league/flysystem-aws-s3-v3 "^3.0"
3. Publish required migrations:
     php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
     php artisan queue:table
     php artisan queue:failed-table
     php artisan queue:batches-table
4. Edit the Sanctum migration so `tokenable_id` is CHAR(36) (see SPEC.md §4.6 warning).
5. Create the App\Enums\ProcessingStatus enum with cases pending, processing, completed, failed.
6. Write migrations in this exact order (filenames per SPEC.md §3.2):
   - create_albums_table               — without cover_photo_id FK
   - create_tags_table
   - create_photos_table               — with album_id FK (ON DELETE SET NULL, ON UPDATE CASCADE)
   - add_cover_photo_fk_to_albums      — adds cover_photo_id FK (ON DELETE SET NULL)
   - create_photo_tag_table            — composite PK, both FKs CASCADE
   All column types, nullability, defaults, and indexes MUST match SPEC.md §4 exactly.
7. Create the Eloquent models (User, Album, Tag, Photo) with HasUuids and the relationships in SPEC.md §5. Cast `exif` to array, `processing_status` to ProcessingStatus enum, `is_favorite` to boolean, `file_size`/`width`/`height` to integer.
8. Create App\Observers\PhotoObserver::deleted() that deletes original/thumbnail/medium/large files from Storage::disk('photos') (guard against null paths). Register it in AppServiceProvider.
9. Create a `photos` disk in config/filesystems.php that resolves to 'local' when PHOTOS_DISK=public and to 's3' when PHOTOS_DISK=s3 (SPEC.md §11.4).
10. Create database/factories/{PhotoFactory,AlbumFactory,TagFactory}.
11. Create database/seeders/AdminUserSeeder that reads ADMIN_EMAIL and ADMIN_PASSWORD from env. Wire it into DatabaseSeeder.
12. Update .env.example with every var from SPEC.md Appendix A (backend local section).

Verify: `php artisan migrate:fresh --seed` runs with zero errors. `php artisan tinker` can do `Photo::factory()->create()` and the row has a UUID id.

Commit: `feat(backend): database schema, models, and seeders`
```

**Verify:**
```bash
cd backend && php artisan migrate:fresh --seed && php artisan tinker --execute="echo App\Models\Photo::factory()->create()->id;"
```

---

### Phase 2 — API layer (controllers, routes, resources, requests)
**Goal:** Every endpoint in SPEC.md §6 is live, wired to Sanctum auth where required, returns the exact response shapes and error envelopes.

**Prompt:**
```
Implement the REST API v1 per SPEC.md §6 and §10.

1. Create App\Http\Middleware\ForceJsonResponse that sets Accept: application/json; register it in bootstrap/app.php for the /api/* group.
2. In bootstrap/app.php, customize the exception handler so every API exception produces the envelope in SPEC.md §10.1 — map 401/403/404/413/415/422/429/500 with the documented `message` (and `errors` for 422). Use $exceptions->shouldRenderJsonWhen(fn ($req) => $req->is('api/*')).
3. Generate form requests under app/Http/Requests/ matching every request body / form in SPEC.md §6:
   - Auth: RegisterRequest, LoginRequest
   - Photo: StorePhotoRequest (multipart, photo field), UpdatePhotoRequest, BatchUploadRequest (files[] 2–20)
   - Album: StoreAlbumRequest, UpdateAlbumRequest
   Every validation rule — MIME allow-list, max:10240, min:2|max:20 for batch, unique names — MUST exactly match the spec.
4. Generate App\Http\Resources\PhotoResource, AlbumResource, TagResource. Resource shapes MUST match SPEC.md §6.5 exactly (urls.{original,thumbnail,medium,large}, nested album and tags, nullable rules).
5. Generate API controllers under app/Http/Controllers/Api/V1/:
   - AuthController (register, login, logout, me)
   - PhotoController (index, show, store, update, destroy, favorite)
   - BatchUploadController (store, show) — wrap ProcessPhoto dispatches in Bus::batch
   - AlbumController (index, show, store, update, destroy — loadCount('photos'))
   - TagController (index — withCount('photos'))
   Implement all query-param filtering/sorting/pagination for GET /photos exactly per SPEC.md §6.2.
6. Register routes in routes/api.php under prefix 'v1'. Group mutating routes under middleware('auth:sanctum'). Apply throttle:10,1 to /auth/*, throttle:120,1 elsewhere.
7. Write Pest tests covering every row of SPEC.md §12.1 under tests/Feature/Api/V1/. Use Storage::fake('photos') and Queue::fake(); assert ProcessPhoto is dispatched but do not run it.
8. All tests MUST pass before committing.

Verify:
  php artisan test --testsuite=Feature
  curl -s http://localhost:8000/api/v1/photos | jq .meta  # should return pagination meta

Commit: `feat(backend): REST API v1 with Sanctum auth`
```

**Verify:**
```bash
cd backend && php artisan test --testsuite=Feature
```

---

### Phase 3 — Filament admin panel (resources, widgets, dashboard)
**Goal:** `/admin` login works with the seeded user and all three resources + widgets + storage page are functional.

**Prompt:**
```
Build the Filament admin panel per SPEC.md §7.

1. php artisan filament:install --panels   # creates App\Providers\Filament\AdminPanelProvider
2. Configure the panel in AdminPanelProvider: path 'admin', primary color #6366F1, brand name "PhotoGallery Pro Admin", login enabled, authGuard('web').
3. Scaffold resources:
     php artisan make:filament-resource Photo --generate
     php artisan make:filament-resource Album --generate
     php artisan make:filament-resource Tag --generate
4. PhotoResource (SPEC.md §7.1):
   - Table columns, filters, bulk actions, reprocess action — match the spec exactly.
   - Form: FileUpload on 'original_path' disk 'photos' directory 'originals', image preview, max 10240 KB, accepted image/jpeg,image/png,image/webp. On afterStateUpdated or afterCreate, dispatch ProcessPhoto.
   - Select on album_id with ->relationship('album', 'name')->createOptionForm([...])
   - Select on tags with ->relationship('tags', 'name')->multiple()->preload()->createOptionForm([TextInput name required]).
5. AlbumResource (SPEC.md §7.2) — table columns per spec, unique name validation with ignoreRecord, PhotosRelationManager attached via getRelations().
6. TagResource (SPEC.md §7.3) — afterStateUpdated on name sets slug via Str::slug.
7. Create widgets under app/Filament/Widgets/:
   - StatsOverview — 4 stats cards (photos, albums, tags, storage formatted bytes)
   - RecentUploadsTable — last 10 photos
   - QueueMonitor — pending/failed jobs from jobs and failed_jobs tables
   Register all three in AdminPanelProvider->widgets([...]).
8. Create App\Filament\Pages\StorageManagement extending Page (SPEC.md §7.5). Include buttons that dispatch ProcessPhoto for every photo and truncate failed_jobs.
9. Verify login with the AdminUserSeeder credentials (ADMIN_EMAIL/ADMIN_PASSWORD from .env).

Verify:
  php artisan serve &
  # visit http://localhost:8000/admin, log in, confirm every resource list renders and dashboard widgets show numbers.

Commit: `feat(backend): Filament admin panel with resources and widgets`
```

**Verify:** `php artisan serve` → open `/admin`, log in, confirm every resource list page renders without errors.

---

### Phase 4 — React frontend setup (scaffold, Tailwind v4, API service)
**Goal:** Vite + React 19 + Tailwind v4 running, dev server proxies `/api` to Laravel, axios client wired with Sanctum token storage.

**Prompt:**
```
Scaffold the React SPA per SPEC.md §3.3 and §8.10–§8.12.

1. From repo root:
     npm create vite@latest frontend -- --template react-ts
     cd frontend
     npm install react-router-dom@^7 @tanstack/react-query@^5 axios@^1 lucide-react sonner
     npm install -D @tailwindcss/vite tailwindcss@^4 vitest @testing-library/react @testing-library/user-event @testing-library/jest-dom jsdom @playwright/test

2. CRITICAL Tailwind v4 rules (SPEC.md §8.11–§8.12):
   - DO NOT create tailwind.config.js
   - DO NOT create postcss.config.js
   - DO NOT install autoprefixer
   - Theme customization goes ONLY inside `@theme { ... }` in src/index.css

3. Replace vite.config.ts with the config in SPEC.md §8.11 — plugins [react(), tailwindcss()], proxy '/api' and '/storage' to http://localhost:8000.

4. Replace src/index.css with the content in SPEC.md §8.12: `@import "tailwindcss";` plus a @theme block with --color-brand-500 and --font-sans.

5. Create the full folder structure in SPEC.md §3.3 as empty placeholder files (keep .gitkeep only where needed). Do not implement components yet — those come in Phase 5.

6. Create src/types/{photo,album,tag}.ts as exact TypeScript mirrors of the resource shapes in SPEC.md §6.5. Include PhotoResource, AlbumResource, TagResource, PaginatedResponse<T>.

7. Create src/api/client.ts exactly per SPEC.md §8.10:
   - axios instance, baseURL '/api/v1'
   - request interceptor attaches Bearer token from localStorage key 'pgp_token'
   - response interceptor: 401 → clear token + redirect to /login; 413/415/5xx → sonner toast; 422 → bubble up to caller
8. Create src/lib/queryClient.ts with a QueryClient configured: defaultOptions.queries.staleTime=30_000, retry=1.
9. Create src/api/{photos,albums,tags,auth}.ts as typed thin wrappers around client — one function per endpoint in SPEC.md §6, matching exact URL/method/params.
10. Create src/data/{copy,nav,shortcuts}.ts — placeholder exports with all strings the UI will need (pulled from the spec sections). NO hardcoded strings in future components.
11. App.tsx and main.tsx — wire QueryClientProvider, BrowserRouter, sonner Toaster. Routes from SPEC.md §8.1 but each page is a stub like `<div>GalleryPage</div>` for now.

Verify:
  npm run dev              # opens Vite at :5173
  curl -I http://localhost:5173/api/v1/photos    # should proxy to Laravel
  npm run build            # must succeed with zero TS errors

Commit: `feat(frontend): Vite + React 19 + Tailwind v4 scaffold with API client`
```

**Verify:**
```bash
cd frontend && npm run build && npm run dev &
curl -sI http://localhost:5173/api/v1/photos | head -1
```

---

### Phase 5 — React components (grid, lightbox, upload, sidebar, keyboard)
**Goal:** Gallery renders real photos, lightbox works with keyboard nav, upload modal uploads to the API, auth gates mutations.

**Prompt:**
```
Implement the frontend UI per SPEC.md §8.

Build in this order (each step must compile before the next):

1. Layout — SPEC.md §8.1–§8.3:
   - components/layout/Shell.tsx — renders Navbar + Sidebar + <Outlet/>
   - components/layout/Navbar.tsx — search input (debounced 300ms via useDebounce), SortDropdown, UploadButton, auth menu
   - components/layout/Sidebar.tsx — All Photos, Favorites, albums list (useAlbums), tags list (useTags) — tag chips toggle ?tags[]= in URL

2. Hooks — src/hooks/:
   - useDebounce(value, delay) — SPEC.md §8.2
   - useAuth() — reads 'pgp_token' from localStorage, exposes login/logout/register calling src/api/auth
   - usePhotos(filters) — useInfiniteQuery wrapping src/api/photos.index; key includes all filter params from SPEC.md §6.2
   - useAlbums(), useTags() — useQuery
   - useKeyboardShortcuts(map) — SPEC.md §8.8; ignore when focused in input/textarea/[contenteditable] EXCEPT `/` which steals focus
   - useUpload() — exposes mutate(files: File[]); 1 file → POST /photos, 2–20 → POST /photos/batch; reports per-file progress via axios onUploadProgress
   - useProcessingPoll(photoIds) — polls GET /photos/:id every 3s until processing_status is 'completed' or 'failed', then invalidates ['photos'] queries

3. Gallery — SPEC.md §8.4–§8.5:
   - components/gallery/MasonryGrid.tsx — CSS columns (columns-2 md:columns-3 lg:columns-4 gap-4), each child has break-inside-avoid; IntersectionObserver sentinel fetches next page
   - components/gallery/PhotoCard.tsx — lazy <img>, aspect-ratio from width/height, hover overlay with title + heart toggle, click sets ?photo=:id
   - components/gallery/ProcessingOverlay.tsx — spinner when pending/processing, red error icon when failed

4. Lightbox — SPEC.md §8.6:
   - components/gallery/PhotoLightbox.tsx — portal, backdrop, left/right nav (arrows + ← →), inline edit title/description/tags, Delete confirm, EXIF side panel, close on Escape/backdrop/URL change
   - components/gallery/ExifPanel.tsx

5. Upload — SPEC.md §8.7:
   - components/upload/UploadModal.tsx — opens from Navbar
   - components/upload/DropZone.tsx — native HTML5 drag events, click-to-browse
   - components/upload/UploadProgressList.tsx — per-file progress row
   Client-side validation: MIME in {image/jpeg, image/png, image/webp}, size ≤ 10 MB BEFORE hitting the network.

6. Pages — wire real data:
   - GalleryPage, FavoritesPage, AlbumPage read filters from URL search params and feed usePhotos
   - LoginPage — email+password form, calls useAuth.login, redirects to intended route

7. All UI strings come from src/data/copy.ts — NO hardcoded strings in JSX.

8. Write Vitest tests for useDebounce, useKeyboardShortcuts, useUpload (validation), UploadModal (drag-drop happy path) — SPEC.md §12.2.

Verify:
  npm test -- --run          # Vitest passes
  npm run build              # TypeScript clean
  # Manual: open http://localhost:5173, log in, upload a photo, verify it shows in the grid, open lightbox, arrow-key navigation works.

Commit: `feat(frontend): gallery, lightbox, upload, keyboard shortcuts`
```

**Verify:** `npm test -- --run && npm run build` — plus the manual golden-path check.

---

### Phase 6 — Image processing pipeline (job, queue, batch)
**Goal:** `ProcessPhoto` job resizes to 3 sizes, extracts EXIF, respects retry+backoff, and `Bus::batch` tracks batch progress.

**Prompt:**
```
Implement the image processing pipeline per SPEC.md §11 and §4 (photos status columns).

1. App\Services\ImageProcessor:
   - Constructor takes a Storage disk name (defaults to 'photos').
   - Method generate(Photo $photo) produces thumbnail (300w, q80, JPEG), medium (800w, q85, JPEG), large (1600w, q90, JPEG).
   - Use Intervention Image v3: ImageManager::imagick() or ImageManager::gd().
   - Never upscale — if source width < target, copy source as that size.
   - Preserve aspect ratio, auto-orient from EXIF.
   - Output paths: photos/thumbnails/{id}.jpg, photos/medium/{id}.jpg, photos/large/{id}.jpg.

2. App\Services\ExifExtractor:
   - Method extract(string $absolutePath): array
   - Return keys: camera, iso, aperture, shutter, focal_length, taken_at (ISO-8601)
   - Gracefully return [] when EXIF is missing or unreadable.

3. App\Jobs\ProcessPhoto implements ShouldQueue:
   - public int $tries = 3;
   - public array $backoff = [10, 30, 60];
   - handle(): set processing_status=processing, generate sizes, save width/height/paths, extract EXIF, set processing_status=completed, increment processing_attempts.
   - failed(Throwable $e): set processing_status=failed, processing_error=$e->getMessage().
   - Use Bus::batch aware behavior if batch context exists.

4. Wire PhotoController@store and BatchUploadController@store to dispatch ProcessPhoto (single) / Bus::batch of ProcessPhoto (batch) after row creation. Batch endpoint returns {batch_id, total, photo_ids}.

5. BatchUploadController@show: return the exact shape in SPEC.md §6.2 for GET /photos/batch/{batchId} — use Bus::findBatch($id).

6. Tests:
   - tests/Unit/Services/ImageProcessorTest — fixture images under tests/Fixtures/; assert never upscales, assert 3 sizes generated with correct widths.
   - tests/Feature/Jobs/ProcessPhotoTest — with real (non-faked) queue: dispatch, assert row updates, status=completed, exif populated.
   - Mock a throwing ImageProcessor to assert failed() sets processing_status=failed after 3 attempts.

7. Local queue worker — document in README.md how to run: `php artisan queue:work --queue=default --tries=3 --timeout=120`.

Verify:
  php artisan test --filter=ProcessPhoto
  # End-to-end: upload a photo via POST /api/v1/photos with a real fixture, start queue:work, poll GET /api/v1/photos/:id until processing_status=completed.

Commit: `feat(backend): image processing pipeline with batch support`
```

**Verify:**
```bash
cd backend && php artisan test --filter=ProcessPhoto
```

---

### Phase 7 — Filament queue monitoring (widgets, reprocess actions)
**Goal:** Admins can see queue health live and reprocess photos individually or in bulk from the admin panel.

**Prompt:**
```
Enhance Filament with queue observability per SPEC.md §7.4–§7.5 (initial widgets were stubbed in Phase 3; now wire real behavior).

1. App\Filament\Widgets\QueueMonitor:
   - 3 stats cards: Pending Jobs (SELECT COUNT(*) FROM jobs), Failed Jobs (SELECT COUNT(*) FROM failed_jobs — red when > 0), Photos Processing (WHERE processing_status='processing').
   - Poll every 5 seconds (->poll('5s')).

2. App\Filament\Widgets\RecentUploadsTable:
   - Last 10 photos by created_at DESC.
   - Columns: thumbnail (ImageColumn from thumbnail_path URL), title, album.name, processing_status badge, created_at (since format).
   - Row action: ViewAction linking to PhotoResource edit page.
   - Poll every 10 seconds.

3. Extend App\Filament\Resources\PhotoResource:
   - Row action `reprocess` — dispatches ProcessPhoto::dispatch($record); resets processing_attempts=0, status=pending; sends Filament Notification::success.
   - Bulk action `reprocess_selected` — same behavior over selected records.
   - Table filter `processing_status` as SelectFilter (all four enum values).

4. App\Filament\Pages\StorageManagement (created as stub in Phase 3):
   - Real implementation: compute disk usage by directory using Storage::disk('photos')->allFiles('originals'|'thumbnails'|'medium'|'large') + sum filesize; format bytes human-readable.
   - Action `regenerate_all_thumbnails` — dispatches ProcessPhoto for every photo (chunked 100 at a time).
   - Action `purge_failed` — truncates failed_jobs; requires confirmation.

5. Register QueueMonitor and RecentUploadsTable in AdminPanelProvider->widgets([...]).

Verify:
  # Upload 3 files, kill the queue worker BEFORE any job runs.
  # Confirm QueueMonitor shows 3 pending; upload a corrupt file to trigger a failure on resume, confirm Failed count increments.
  php artisan test --filter=Filament

Commit: `feat(backend): Filament queue monitoring and reprocess actions`
```

**Verify:** Visit `/admin`, upload a photo with the queue worker stopped, confirm `QueueMonitor` shows 1 pending; start the worker, confirm it drops to 0.

---

### Phase 8 — AWS migration (S3 + SQS via env vars)
**Goal:** Same code, production-ready on S3 + SQS. Only `.env` changes.

**Prompt:**
```
Migrate storage and queueing to AWS without changing any application code per SPEC.md §11.4.

1. Confirm every file operation in app/ uses Storage::disk('photos') — grep for File::, fopen, copy, move_uploaded_file; any hit is a bug, refactor to Storage.
2. Confirm every job dispatch uses ::dispatch() (not ::dispatchSync()); grep verifies.
3. Create .env.production.example at backend/ root with the production block from SPEC.md Appendix A — QUEUE_CONNECTION=sqs, PHOTOS_DISK=s3, all AWS_* and SQS_* vars.
4. Create docs/DEPLOY.md:
   - How to create the S3 bucket (public read on /photos/*, CORS config allowing the production frontend origin).
   - How to create the SQS queue (standard, not FIFO).
   - Supervisor config for `php artisan queue:work sqs --queue=photogallerypro --tries=3 --timeout=120 --sleep=3`.
   - Laravel Horizon is NOT required (we use plain queue:work) — note this explicitly.
   - cron entry: `* * * * * php artisan schedule:run` if scheduled tasks are added later.
5. Create a smoke-test script backend/scripts/smoke-aws.php that:
   - Reads .env.production (via Dotenv)
   - Writes a 1×1 JPEG to Storage::disk('photos')
   - Dispatches a no-op job to SQS
   - Cleans up
   Print PASS/FAIL per step.
6. Add a route-level check in HealthController@aws (GET /api/v1/health/aws) that returns {storage: 'ok'|'error', queue: 'ok'|'error'}; used by uptime monitors. NOT protected by auth — safe read-only.

Verify:
  cp .env.production.example .env.production
  # Fill in real AWS creds for a staging bucket + queue
  APP_ENV=production php backend/scripts/smoke-aws.php    # prints PASS
  curl -s https://staging.example.com/api/v1/health/aws   # {"storage":"ok","queue":"ok"}

Commit: `chore(backend): AWS S3 + SQS production configuration`
```

**Verify:** Smoke script passes against a staging bucket + queue.

---

### Phase 9 — Hooks (auto-format, auto-test, safety)
**Goal:** Claude Code and git hooks enforce formatting, linting, and fast tests on every commit so regressions can't slip in.

**Prompt:**
```
Install pre-commit safety hooks and Claude Code hooks per standard practice.

1. Backend — install dev tools:
     composer require --dev laravel/pint rector/rector
   Create backend/pint.json with Laravel preset.
   Create backend/rector.php with: withPhpSets(php83: true), withPaths([app, database, routes, tests]).

2. Frontend — install dev tools:
     cd frontend && npm install -D prettier eslint @typescript-eslint/eslint-plugin @typescript-eslint/parser eslint-plugin-react-hooks
   Create frontend/.prettierrc with { "singleQuote": true, "semi": true, "printWidth": 100 }.
   Create frontend/eslint.config.js with TypeScript + react-hooks rules.

3. Install a root-level pre-commit hook. Choose lefthook:
     brew install lefthook || npm i -g lefthook
     lefthook install
   Create lefthook.yml at repo root:
     pre-commit:
       parallel: true
       commands:
         pint:
           root: backend/
           glob: "*.php"
           run: ./vendor/bin/pint {staged_files}
         prettier:
           root: frontend/
           glob: "*.{ts,tsx,js,jsx,css}"
           run: npx prettier --write {staged_files}
         eslint:
           root: frontend/
           glob: "*.{ts,tsx}"
           run: npx eslint --fix {staged_files}

4. Create .claude/settings.json (repo-scoped Claude Code config) with hooks:
   - PostToolUse on Edit/Write matching `backend/**/*.php` → runs `./vendor/bin/pint {file_path}` in backend/
   - PostToolUse on Edit/Write matching `frontend/**/*.{ts,tsx}` → runs `npx prettier --write {file_path}` in frontend/
   - UserPromptSubmit safety: blocks destructive commands matching `rm -rf|git push --force|git reset --hard` with a warning prompt.
   (Exact schema: https://docs.claude.com/claude-code — use hooks array with matcher + command.)

5. Add a fast-tests GitHub Actions stub at .github/workflows/ci.yml:
   - job "backend": matrix PHP 8.3, run `composer install --no-interaction`, `php artisan migrate --env=testing`, `php artisan test`.
   - job "frontend": Node 20, `npm ci`, `npm test -- --run`, `npm run build`.

Verify:
  cd backend && ./vendor/bin/pint --test     # exits 0 (clean)
  cd frontend && npx prettier --check .      # exits 0
  git commit -m "test" --allow-empty         # lefthook runs, no errors

Commit: `chore: formatting, linting, and hook infrastructure`
```

**Verify:** Running `git commit --allow-empty -m "test"` triggers lefthook and completes successfully.

---

### Phase 10 — Testing (Pest + Vitest + Playwright)
**Goal:** Full test matrix green. §12 checklist every box checked.

**Prompt:**
```
Achieve the SPEC.md §12 testing checklist. Any phase that skipped tests gets caught up here.

1. Backend (Pest) — complete every item in SPEC.md §12.1 that isn't already written:
   - tests/Feature/Api/V1/PhotoIndexTest — every filter/sort/pagination case as a data provider (Pest dataset()).
   - tests/Feature/Api/V1/AuthProtectionTest — iterate every mutating route, assert 401 without token.
   - tests/Unit/Services/ImageProcessorTest — no-upscale test, aspect-ratio preservation, JPEG output regardless of input (feed a PNG and a WebP).
   - tests/Feature/Jobs/ProcessPhotoTest — 3-attempt failure case using a mocked ImageProcessor that throws.
   Run: php artisan test --parallel

2. Frontend (Vitest) — complete every item in SPEC.md §12.2:
   - hooks/useDebounce.test.ts
   - hooks/useKeyboardShortcuts.test.ts — simulate keydown on window; assert handler fires; assert it skips when document.activeElement is an input (except '/').
   - components/UploadModal.test.tsx — drag-drop a 15 MB file → inline error shown; a text/plain file → rejected.
   Run: npm test -- --run --coverage

3. E2E (Playwright) — SPEC.md §12.3:
   - playwright.config.ts with webServer starting both Laravel (php artisan serve) and Vite (npm run dev) plus a queue:work worker. Pass test env BASE_URL=http://localhost:5173.
   - e2e/upload.spec.ts — login, upload a fixture, poll, assert tile renders.
   - e2e/gallery.spec.ts — seed 10 photos via API, type in search, click tag chip, assert filtered count.
   - e2e/lightbox.spec.ts — open lightbox, ArrowRight cycles, F toggles heart, Escape closes.
   - e2e/auth.spec.ts — logged-out user can browse; click Upload → login modal appears.
   - e2e/batch.spec.ts — drop 5 files; assert progress 5/5 and 5 tiles.
   Run: npx playwright test

4. Coverage gates (soft — warn, don't fail CI): backend ≥ 80% lines (Pest's --coverage), frontend ≥ 70% lines (Vitest v8).

Verify:
  cd backend && php artisan test --parallel         # all green
  cd frontend && npm test -- --run                  # all green
  cd frontend && npx playwright test                # all green

Commit: `test: full Pest + Vitest + Playwright coverage`
```

**Verify:** all three test runners exit 0.

---

### Phase 11 — Git + PR (GitHub MCP)
**Goal:** Work is pushed, PRs are opened with good descriptions, branch protection in place.

**Prompt:**
```
Open the PhotoGallery Pro project on GitHub and land the work there.

Preconditions: you have `gh` installed OR the GitHub MCP server configured in Claude Code. Pick whichever is available.

1. Create the repo (private by default):
     gh repo create photogallerypro --private --source=. --remote=origin --push
   or via MCP: use mcp__github tool to create_repo.
2. Set default branch to `main`. Enable branch protection via gh api:
     - Require pull request before merging
     - Require status checks: backend + frontend (from .github/workflows/ci.yml in Phase 9)
     - Require linear history
3. For each feature commit made in Phases 1–10, the build was done on main so we now migrate history:
     - Create a `develop` branch
     - Force-protect `main` behind PRs going forward (so future work happens on feature branches)
     - This is a one-time correction. NEW work uses feature branches.
4. Add PULL_REQUEST_TEMPLATE.md at .github/ with sections: Summary, Screenshots, Test plan, Spec references (link SPEC.md sections).
5. Add CODEOWNERS with the repo owner.
6. Document the PR workflow in docs/CONTRIBUTING.md:
   - Branch naming: `feat/<topic>`, `fix/<topic>`, `chore/<topic>`.
   - Commit style: conventional commits (feat|fix|chore|test|docs|refactor).
   - Every PR must pass CI and include a screenshot for frontend changes.
7. Open a demo PR from a throwaway branch that tweaks README.md to confirm the template + status checks work.

Verify:
  gh repo view --web              # repo exists
  gh pr list                      # demo PR visible with template filled
  gh api repos/:owner/:repo/branches/main/protection   # protection rules present

Commit (on the demo PR branch): `docs: GitHub workflow and PR template`
```

**Verify:** Demo PR is open on GitHub; CI checks run; branch protection blocks direct push to `main`.

---

### Phase 12 — Multi-agent parallel development
**Goal:** Work on multiple features simultaneously using Claude Code subagents + git worktrees.

**Prompt:**
```
Establish a parallel-development workflow for future feature work using git worktrees + Claude Code subagents.

1. Add docs/PARALLEL-WORKFLOW.md describing the recipe:
   - When to parallelize: 2+ independent tasks touching disjoint directories (backend vs frontend; different resources).
   - When NOT to parallelize: shared migrations, shared types, or anything requiring SPEC.md changes.

2. Set up a worktree helper at scripts/worktree-new.sh:
     #!/usr/bin/env bash
     set -euo pipefail
     name="$1"     # e.g. feat/new-widget
     git worktree add "../pgp-${name//\//-}" -b "$name"
     echo "Worktree at ../pgp-${name//\//-}; start Claude Code there."

3. Demo the workflow by running two concurrent tasks:
   Task A (backend worktree): add a GET /api/v1/stats endpoint returning totals per SPEC.md §7.4 StatsOverview widget (re-use existing counts, just expose as API for the frontend).
   Task B (frontend worktree): add a simple /stats page that calls the new endpoint and displays the 4 numbers — pure display, no interactivity.

   Spawn them in parallel:
     - Claude Code session 1 in ../pgp-feat-api-stats: implement endpoint + test.
     - Claude Code session 2 in ../pgp-feat-page-stats: implement page using a mock until A lands.

4. When both land, merge A first, then update B's mock to real endpoint, then merge B.

5. Document subagent usage in the same doc:
   - Use the Explore agent for broad codebase searches that would cost context in the main session.
   - Use the Plan agent before risky refactors.
   - Dispatch parallel agents ONLY for independent read/write scopes.

Verify:
  bash scripts/worktree-new.sh feat/demo-parallel
  ls -d ../pgp-feat-demo-parallel    # directory exists
  git worktree list                  # shows the new worktree

Commit: `docs: parallel development workflow with worktrees`
```

**Verify:** `git worktree list` shows the demo worktree; both sample PRs land cleanly without conflicts.

---

### Phase 13 — Final spec check + deploy
**Goal:** Every SPEC.md section has shipping code; staging is live; smoke test passes; production cutover plan ready.

**Prompt:**
```
Final verification pass and staging deploy.

1. Spec coverage audit — produce docs/SPEC-COVERAGE.md mapping every subsection of SPEC.md to:
   - Code location (file or folder)
   - Test coverage (file path)
   - Status: ✅ Done / ⚠️ Partial / ❌ Missing
   Any row that is not ✅ is a blocker — stop here and open issues.

2. Accessibility pass — SPEC.md §8 emphasizes a11y; run axe-core against the running frontend:
     npx @axe-core/cli http://localhost:5173
   Fix every critical/serious issue. Add alt text on every <img>, aria-labels on icon buttons, focus trap in Modal + Lightbox.

3. Performance pass — Lighthouse:
     npx lighthouse http://localhost:5173 --preset=desktop --output=html --output-path=./lighthouse.html
   Target: Performance ≥ 90, Accessibility ≥ 95. Fix any regression.

4. Security pass:
   - npm audit --production            # frontend — zero high/critical
   - composer audit                    # backend — zero high/critical
   - Verify CORS allows only FRONTEND_URL, not *.
   - Verify Sanctum token expiration is configured (30 days per SPEC.md §9).
   - Verify no .env.* files are committed.

5. Staging deploy:
   - Provision AWS resources per docs/DEPLOY.md.
   - Deploy backend to a single EC2/ECS task with supervisor running queue:work sqs.
   - Deploy frontend build (npm run build; dist/) to S3 + CloudFront.
   - Configure CloudFront behavior: /api/* forwards to EC2 ALB; everything else serves from S3 with SPA fallback to index.html.
   - Run the Phase 8 smoke-aws.php against staging — must PASS.
   - Run Playwright E2E against staging URL with BASE_URL=https://staging.example.com.

6. Production cutover checklist (in docs/DEPLOY.md):
   - [ ] Database backed up before first deploy.
   - [ ] S3 lifecycle rules: incomplete multipart uploads purged after 7 days.
   - [ ] CloudWatch alarms: SQS ApproximateNumberOfMessagesVisible > 100 for 10 min → SNS.
   - [ ] Sentry DSN configured for backend and frontend.
   - [ ] Rollback plan documented.

Verify:
  docs/SPEC-COVERAGE.md has zero ❌ rows.
  Staging E2E suite passes.
  Lighthouse thresholds met.

Commit: `chore: spec coverage audit and staging deploy docs`

After staging verifies clean, create a GitHub release:
  gh release create v1.0.0 --generate-notes
```

**Verify:** staging is reachable, E2E green against staging URL, Lighthouse ≥ targets, spec coverage doc has zero gaps.

---

### Workflow summary table

| Phase | Focus | Deliverable commit | Blocker for |
|---|---|---|---|
| 1 | Backend foundation | `feat(backend): database schema, models, and seeders` | 2, 6 |
| 2 | API layer | `feat(backend): REST API v1 with Sanctum auth` | 5 |
| 3 | Filament admin | `feat(backend): Filament admin panel with resources and widgets` | 7 |
| 4 | Frontend scaffold | `feat(frontend): Vite + React 19 + Tailwind v4 scaffold with API client` | 5 |
| 5 | React components | `feat(frontend): gallery, lightbox, upload, keyboard shortcuts` | 10 |
| 6 | Image pipeline | `feat(backend): image processing pipeline with batch support` | 7, 8 |
| 7 | Queue monitoring | `feat(backend): Filament queue monitoring and reprocess actions` | — |
| 8 | AWS migration | `chore(backend): AWS S3 + SQS production configuration` | 13 |
| 9 | Hooks | `chore: formatting, linting, and hook infrastructure` | 10, 11 |
| 10 | Testing | `test: full Pest + Vitest + Playwright coverage` | 13 |
| 11 | Git + PR | `docs: GitHub workflow and PR template` | 12 |
| 12 | Multi-agent | `docs: parallel development workflow with worktrees` | — |
| 13 | Spec check + deploy | `chore: spec coverage audit and staging deploy docs` | — (final) |

---

## Appendix A — Environment variables (reference)

### Backend `.env`
```
APP_NAME=PhotoGalleryPro
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photogallerypro
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database   # prod: sqs
FILESYSTEM_DISK=photos
PHOTOS_DISK=public          # prod: s3

# prod-only:
# SQS_KEY=...
# SQS_SECRET=...
# SQS_PREFIX=...
# SQS_QUEUE=photogallerypro
# SQS_REGION=eu-west-1
# AWS_BUCKET=photogallery-pro-prod
# AWS_URL=https://cdn.example.com

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=changeme
```

### Frontend `.env`
```
VITE_API_BASE=/api/v1
```

---

**End of SPEC.md**
