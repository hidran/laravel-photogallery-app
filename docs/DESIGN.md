# PhotoGallery Pro — Engineering Design

> The technical blueprint. Authoritative for code: file paths, schemas, contracts, status codes. Pairs with `REQUIREMENTS.md` (what to build) and `TASKS.md` (how to execute). Workflow rules live in `CLAUDE.md`.

---

## 1. Architecture overview

Two deployable artifacts in one monorepo:

```
laravelapp/
├── CLAUDE.md
├── README.md
├── docs/
│   ├── REQUIREMENTS.md        ← business layer
│   ├── DESIGN.md              ← this file (engineering layer)
│   ├── TASKS.md               ← execution layer (124 tasks, 14 phases)
│   ├── SPEC-REVIEW.md         ← architectural decision log
│   ├── SPEC-COVERAGE.md       ← implementation audit (DESIGN.md → code)
│   ├── REVIEW-FIXES.md        ← code review fix log
│   ├── CONTRIBUTING.md        ← contributor guide
│   ├── PARALLEL-WORKFLOW.md   ← git worktree patterns
│   └── BATCH-*.md             ← per-session work logs
├── backend/                   ← Laravel 13 API + Filament admin
└── frontend/                  ← React 19 + Vite 6 + Tailwind v4 SPA
```

The two artifacts communicate via a JSON REST API at `/api/v1`. Backend handles persistence, auth, file uploads, and asynchronous image processing. Frontend renders the public gallery and member tools. Filament v5 (mounted inside the Laravel app at `/admin`) is the admin panel.

**Same code, two environments:**
- Local dev: SQLite/MySQL + database queue + local filesystem disks
- Production: MySQL/Postgres + AWS SQS + AWS S3 (split into a public bucket for variants and a private bucket for originals)

Only env vars change.

---

## 2. Tech stack

| Layer | Technology | Version | Notes |
|---|---|---|---|
| Backend runtime | PHP | 8.3+ | Strict types where practical |
| Backend framework | Laravel | 13.x | New skeleton; `bootstrap/app.php`-style config |
| Admin panel | Filament | 5.x | Mounted at `/admin` |
| Auth (SPA) | Sanctum | 4.x | **Token mode** (24-hour TTL) |
| Auth (admin) | Laravel session | built-in | `web` guard |
| Image processing | Intervention Image | 3.x | Via `intervention/image-laravel` |
| Queue (dev) | Database | — | `jobs` + `failed_jobs` tables |
| Queue (prod) | AWS SQS | — | Standard queue, not FIFO |
| Storage (dev) | Local | — | `storage/app/photos-private/` + `storage/app/public/photos/` |
| Storage (prod) | AWS S3 | — | Two buckets: public (CDN-fronted) + private (Block Public Access) |
| Backend tests | Pest | 3.x | Feature + unit |
| Code style (PHP) | Pint | latest | Laravel preset |
| Modernization | Rector | latest | PHP 8.3 sets |
| Query auditing (dev/test) | beyondcode/laravel-query-detector | latest | Catches N+1 |
| Frontend runtime | Node.js | 20 LTS+ | |
| Frontend framework | React | 19.x | Function components only |
| Bundler | Vite | 6.x | `@vitejs/plugin-react` |
| Styling | Tailwind CSS | 4.x | `@tailwindcss/vite` only — see §8.12 |
| Routing | React Router | 7.x | Data router API + lazy routes |
| Server state | TanStack Query | 5.x | Polling, caching, invalidation |
| HTTP | Axios | 1.x | Auth + error interceptors |
| Focus management | react-focus-lock | latest | Modal + lightbox |
| Icons | lucide-react | latest | |
| Toasts | sonner | latest | |
| Frontend tests | Vitest + React Testing Library | latest | jsdom env |
| E2E | Playwright | latest | Plus axe-core for a11y |
| Code style (TS) | Prettier + ESLint | latest | Strict TS |

---

## 3. Folder / file architecture

### 3.1 Monorepo root

```
laravelapp/
├── CLAUDE.md
├── docs/
├── backend/
└── frontend/
```

### 3.2 Backend (Laravel)

```
backend/
├── app/
│   ├── Actions/                            ← Single-purpose, invokable use-case classes
│   │   ├── Photo/
│   │   │   ├── UploadPhotosAction.php          # POST /photos — orchestrates DB::transaction
│   │   │   ├── UpdatePhotoAction.php           # PATCH /photos/{id}
│   │   │   ├── MarkFavoriteAction.php          # PUT /photos/{id}/favorite
│   │   │   └── UnmarkFavoriteAction.php        # DELETE /photos/{id}/favorite
│   │   └── Album/
│   │       ├── CreateAlbumAction.php
│   │       ├── UpdateAlbumAction.php
│   │       └── DeleteAlbumAction.php
│   ├── Concerns/
│   │   └── HasUuidV7.php                   # Trait — overrides newUniqueId() with Str::uuid7()
│   ├── Contracts/                          ← Interfaces (DIP)
│   │   ├── ImageProcessor.php              # See §9
│   │   ├── ExifExtractor.php               # See §9
│   │   └── PhotoStorage.php                # Abstracts the two-disk model — see §9
│   ├── Enums/
│   │   ├── ProcessingStatus.php            # pending | processing | completed | failed
│   │   └── TokenAbility.php                # photos:write | albums:write | admin
│   ├── Events/
│   │   ├── PhotoUploaded.php
│   │   ├── PhotoProcessed.php
│   │   ├── PhotoDeleted.php
│   │   └── AlbumDeleted.php
│   ├── Filament/
│   │   ├── Pages/
│   │   │   └── StorageManagement.php
│   │   ├── Resources/                      ← Filament admin resources
│   │   │   ├── PhotoResource.php
│   │   │   ├── PhotoResource/
│   │   │   │   └── Pages/{ListPhotos,CreatePhoto,EditPhoto,ViewPhoto}.php
│   │   │   ├── AlbumResource.php
│   │   │   ├── AlbumResource/
│   │   │   │   ├── Pages/{ListAlbums,CreateAlbum,EditAlbum}.php
│   │   │   │   └── RelationManagers/PhotosRelationManager.php
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
│   │   │       ├── BatchController.php         # GET /photos/batch/{id}
│   │   │       ├── HealthController.php        # GET /health
│   │   │       ├── PhotoController.php
│   │   │       └── TagController.php
│   │   ├── Middleware/
│   │   │   ├── ForceJsonResponse.php
│   │   │   └── ETag.php                        # Adds ETag, handles If-None-Match → 304
│   │   ├── Requests/                       ← Form Requests (input validation)
│   │   │   ├── Album/
│   │   │   │   ├── StoreAlbumRequest.php
│   │   │   │   └── UpdateAlbumRequest.php
│   │   │   ├── Auth/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   └── RegisterRequest.php
│   │   │   └── Photo/
│   │   │       ├── IndexPhotosRequest.php
│   │   │       ├── StorePhotosRequest.php
│   │   │       └── UpdatePhotoRequest.php
│   │   └── Resources/                      ← API Resources (output shaping)
│   │       ├── AlbumData.php                   # NB: renamed from AlbumResource to avoid Filament collision
│   │       ├── PhotoData.php
│   │       ├── TagData.php
│   │       └── UserData.php
│   ├── Jobs/
│   │   └── ProcessPhoto.php                # Single pipeline job; batch is just Bus::batch([ProcessPhoto, ...])
│   ├── Listeners/                          # Domain event handlers
│   ├── Models/
│   │   ├── Album.php
│   │   ├── Photo.php
│   │   ├── Tag.php
│   │   └── User.php
│   ├── Observers/
│   │   └── PhotoObserver.php               # Deletes files from photos + photos_private on model delete
│   ├── Policies/                           ← Authorization
│   │   ├── AlbumPolicy.php
│   │   └── PhotoPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php          # Binds ImageProcessor, ExifExtractor, PhotoStorage contracts
│   │   ├── AuthServiceProvider.php         # Registers policies + Gate::define('admin', ...)
│   │   └── Filament/
│   │       └── AdminPanelProvider.php
│   ├── Queries/                            ← Composable query builders
│   │   └── PhotoQuery.php
│   └── Services/
│       ├── Imaging/
│       │   ├── InterventionImageProcessor.php  # implements Contracts\ImageProcessor
│       │   └── PhpExifExtractor.php            # implements Contracts\ExifExtractor
│       ├── Storage/
│       │   └── DiskPhotoStorage.php            # implements Contracts\PhotoStorage
│       └── TagAssigner.php                 # syncByNames(Photo, array $names): void
├── bootstrap/
│   └── app.php                             # Routes + middleware + exception handling
├── config/
│   ├── filament.php
│   ├── filesystems.php                     # 'photos' + 'photos_private' disks
│   ├── photogallery.php                    # NEW: app-specific config (sizes, polling, budgets)
│   └── sanctum.php
├── database/
│   ├── factories/
│   │   ├── AlbumFactory.php
│   │   ├── PhotoFactory.php
│   │   └── TagFactory.php
│   ├── migrations/                         # Filenames generated by `make:migration`
│   │   ├── <ts>_create_users_table.php
│   │   ├── <ts>_create_albums_table.php                  # no cover_photo_id FK yet
│   │   ├── <ts>_create_tags_table.php
│   │   ├── <ts>_create_photos_table.php                  # FULLTEXT(title, description)
│   │   ├── <ts>_add_cover_photo_fk_to_albums.php         # resolves circular FK
│   │   ├── <ts>_create_photo_tag_table.php
│   │   ├── <ts>_create_personal_access_tokens_table.php  # tokenable_id CHAR(36)
│   │   ├── <ts>_create_jobs_table.php
│   │   ├── <ts>_create_failed_jobs_table.php
│   │   └── <ts>_create_job_batches_table.php
│   └── seeders/
│       ├── AdminUserSeeder.php
│       ├── DatabaseSeeder.php
│       └── Test/
│           ├── PhotoSeeder.php             # Used by E2E fixtures
│           └── TagSeeder.php
├── routes/
│   ├── api.php                             # /api/v1/*
│   ├── console.php
│   └── web.php                             # `/` redirects to `/admin`
├── scripts/
│   └── smoke-aws.php                       # Verifies S3 + SQS connectivity
├── storage/
│   ├── app/
│   │   ├── photos-private/                 # Originals (local dev)
│   │   └── public/photos/                  # Sized variants (local dev)
│   └── logs/
├── tests/
│   ├── Doubles/
│   │   ├── FakeImageProcessor.php
│   │   └── FakeExifExtractor.php
│   ├── Feature/
│   │   ├── Api/V1/
│   │   │   ├── AuthTest.php
│   │   │   ├── AuthorizationTest.php       # Per-endpoint 401/403 sweep
│   │   │   ├── PhotoIndexTest.php
│   │   │   ├── PhotoCrudTest.php
│   │   │   ├── PhotoUploadTest.php
│   │   │   ├── PhotoFavoriteTest.php
│   │   │   ├── BatchProgressTest.php
│   │   │   ├── AlbumCrudTest.php
│   │   │   ├── TagIndexTest.php
│   │   │   └── HealthTest.php
│   │   └── Jobs/
│   │       └── ProcessPhotoTest.php
│   └── Unit/
│       ├── Queries/PhotoQueryTest.php
│       └── Services/
│           ├── ImageProcessorTest.php
│           └── TagAssignerTest.php
├── .env.example
├── .env.production.example
├── composer.json
├── pint.json
├── phpunit.xml
└── rector.php
```

### 3.3 Frontend (React SPA)

```
frontend/
├── public/
│   └── favicon.svg
├── src/
│   ├── api/                                ← Typed API wrappers (no axios calls outside)
│   │   ├── client.ts
│   │   ├── photos.ts
│   │   ├── albums.ts
│   │   ├── tags.ts
│   │   ├── batch.ts
│   │   └── auth.ts
│   ├── components/
│   │   ├── common/
│   │   │   ├── Button.tsx
│   │   │   ├── ConfirmDialog.tsx
│   │   │   ├── EmptyState.tsx
│   │   │   ├── ErrorBoundary.tsx
│   │   │   ├── Modal.tsx                       # uses react-focus-lock
│   │   │   └── Spinner.tsx
│   │   ├── gallery/
│   │   │   ├── ExifPanel.tsx
│   │   │   ├── MasonryGrid.tsx
│   │   │   ├── PhotoCard.tsx
│   │   │   ├── PhotoLightbox.tsx
│   │   │   └── ProcessingOverlay.tsx
│   │   ├── layout/
│   │   │   ├── Navbar.tsx
│   │   │   ├── Shell.tsx
│   │   │   └── Sidebar.tsx
│   │   ├── providers/
│   │   │   └── KeyboardShortcutsProvider.tsx
│   │   └── upload/
│   │       ├── DropZone.tsx
│   │       ├── UploadModal.tsx
│   │       └── UploadProgressList.tsx
│   ├── data/                               ← All UI strings + config constants
│   │   ├── copy.ts
│   │   ├── nav.ts
│   │   ├── polling.ts                          # BATCH_MS, PHOTO_MS, QUEUE_MS, DEBOUNCE_MS
│   │   └── shortcuts.ts
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── useBatchPoll.ts                     # Single polling loop after upload
│   │   ├── useDebounce.ts
│   │   ├── useKeyboardShortcuts.ts
│   │   ├── usePhotos.ts                        # TanStack Query — useInfiniteQuery
│   │   ├── useAlbums.ts
│   │   ├── useTags.ts
│   │   └── useUpload.ts
│   ├── lib/
│   │   ├── formatBytes.ts
│   │   └── queryClient.ts
│   ├── pages/
│   │   ├── AlbumPage.tsx
│   │   ├── FavoritesPage.tsx
│   │   ├── GalleryPage.tsx
│   │   ├── LoginPage.tsx
│   │   └── NotFoundPage.tsx
│   ├── types/
│   │   ├── album.ts
│   │   ├── photo.ts
│   │   ├── tag.ts
│   │   └── api.ts                              # PaginatedResponse<T>, ApiError, etc.
│   ├── App.tsx
│   ├── main.tsx
│   └── index.css                               # @import "tailwindcss"; @theme {}; reduced-motion
├── tests/
│   ├── components/
│   │   ├── PhotoLightbox.test.tsx
│   │   └── UploadModal.test.tsx
│   └── hooks/
│       ├── useDebounce.test.ts
│       ├── useKeyboardShortcuts.test.ts
│       └── useUpload.test.ts
├── e2e/
│   ├── auth.spec.ts
│   ├── batch.spec.ts
│   ├── gallery.spec.ts
│   ├── lightbox.spec.ts
│   └── upload.spec.ts
├── eslint.config.js
├── index.html
├── package.json
├── playwright.config.ts
├── .prettierrc
├── tsconfig.json
└── vite.config.ts
```

---

## 4. Database schema

**Conventions:**
- Primary keys: UUID v7, `CHAR(36)`, **NOT NULL**, generated via `App\Concerns\HasUuidV7::newUniqueId()`.
- Timestamps: `TIMESTAMP`, **NULLABLE** with no DB default; Eloquent populates them.
- Every FK declares both `ON DELETE` and `ON UPDATE` actions (`ON UPDATE` always `CASCADE`).
- Every FK column has an index.
- `exif` JSON column is opaque — never `WHERE exif->...`.
- Soft deletes: out of scope for v1.

**Migration order resolves the circular FK** (`albums.cover_photo_id` ↔ `photos.id`):
1. `albums` (without `cover_photo_id` FK)
2. `photos` (with `album_id` FK)
3. `add_cover_photo_fk_to_albums` (adds the FK via ALTER)

### 4.1 `users`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| name | VARCHAR(255) | NOT NULL | — | — |
| email | VARCHAR(255) | NOT NULL | — | UNIQUE (`users_email_unique`) |
| email_verified_at | TIMESTAMP | NULL | NULL | — |
| password | VARCHAR(255) | NOT NULL | — | bcrypt 60 chars |
| is_admin | TINYINT(1) | NOT NULL | `0` | — |
| remember_token | VARCHAR(100) | NULL | NULL | — |
| created_at | TIMESTAMP | NULL | NULL | Laravel-managed |
| updated_at | TIMESTAMP | NULL | NULL | Laravel-managed |

**Indexes:** PRIMARY (`id`), UNIQUE (`email`), INDEX (`is_admin`).

### 4.2 `albums`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| user_id | CHAR(36) | NOT NULL | — | FK → `users(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| name | VARCHAR(255) | NOT NULL | — | unique-per-user via composite UNIQUE |
| description | TEXT | NULL | NULL | — |
| cover_photo_id | CHAR(36) | NULL | NULL | FK → `photos(id)` · ON DELETE **SET NULL** · ON UPDATE **CASCADE** |
| created_at | TIMESTAMP | NULL | NULL | — |
| updated_at | TIMESTAMP | NULL | NULL | — |

**Indexes:** PRIMARY (`id`), UNIQUE (`user_id`, `name`) `albums_user_name_unique`, INDEX (`cover_photo_id`).

### 4.3 `tags`

Tags are **global** (no `user_id`). Two members tagging "sunset" reference the same tag id.

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| name | VARCHAR(100) | NOT NULL | — | UNIQUE (`tags_name_unique`) |
| slug | VARCHAR(120) | NOT NULL | — | UNIQUE (`tags_slug_unique`) |
| created_at | TIMESTAMP | NULL | NULL | — |
| updated_at | TIMESTAMP | NULL | NULL | — |

Slug auto-generated from `name` in the model's `booted()` hook via `Str::slug`. Collisions resolved with `-2`, `-3`, ….

### 4.4 `photos`

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| id | CHAR(36) | NOT NULL | — | PRIMARY KEY |
| user_id | CHAR(36) | NOT NULL | — | FK → `users(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** (owner) |
| title | VARCHAR(255) | NOT NULL | — | — |
| description | TEXT | NULL | NULL | — |
| filename | VARCHAR(255) | NOT NULL | — | original user filename |
| original_path | VARCHAR(500) | NOT NULL | — | path on `photos_private` disk |
| thumbnail_path | VARCHAR(500) | NULL | NULL | path on `photos` disk (set by job) |
| medium_path | VARCHAR(500) | NULL | NULL | path on `photos` disk (set by job) |
| large_path | VARCHAR(500) | NULL | NULL | path on `photos` disk (set by job) |
| album_id | CHAR(36) | NULL | NULL | FK → `albums(id)` · ON DELETE **SET NULL** · ON UPDATE **CASCADE** |
| width | INT UNSIGNED | NULL | NULL | filled after processing |
| height | INT UNSIGNED | NULL | NULL | filled after processing |
| file_size | BIGINT UNSIGNED | NOT NULL | — | bytes |
| mime_type | VARCHAR(100) | NOT NULL | — | — |
| ~~is_favorite~~ | — | — | — | **REMOVED** — replaced by `favorites` pivot table (§4.7) |
| exif | JSON | NULL | NULL | sanitized — no GPS |
| processing_status | ENUM | NOT NULL | `'pending'` | `pending` / `processing` / `completed` / `failed` |
| processing_attempts | TINYINT UNSIGNED | NOT NULL | `0` | — |
| processing_error | TEXT | NULL | NULL | — |
| created_at | TIMESTAMP | NULL | NULL | — |
| updated_at | TIMESTAMP | NULL | NULL | — |

**Indexes:**
- PRIMARY (`id`)
- INDEX (`user_id`)
- INDEX (`album_id`, `created_at`) — covers "newest in album"
- ~~INDEX (`is_favorite`, `created_at`)~~ — **REMOVED** (favorites now in pivot table)
- INDEX (`processing_status`, `created_at`) — covers admin queue dashboard
- INDEX (`created_at`) — default newest sort across all photos
- FULLTEXT (`title`, `description`) `photos_search_idx` — for `MATCH ... AGAINST` search

### 4.5 `photo_tag` (pivot)

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| photo_id | CHAR(36) | NOT NULL | — | FK → `photos(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| tag_id | CHAR(36) | NOT NULL | — | FK → `tags(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| created_at | TIMESTAMP | NOT NULL | `CURRENT_TIMESTAMP` | when attached |

**Keys / Indexes:**
- **Composite PRIMARY KEY** (`photo_id`, `tag_id`)
- INDEX (`tag_id`)

### 4.6 `favorites` (pivot — users ↔ photos)

| Column | Type | Null | Default | Key / Constraint |
|---|---|---|---|---|
| user_id | CHAR(36) | NOT NULL | — | FK → `users(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| photo_id | CHAR(36) | NOT NULL | — | FK → `photos(id)` · ON DELETE **CASCADE** · ON UPDATE **CASCADE** |
| created_at | TIMESTAMP | NOT NULL | `CURRENT_TIMESTAMP` | when favorited |

**Keys / Indexes:**
- **Composite PRIMARY KEY** (`user_id`, `photo_id`)
- INDEX (`photo_id`) — for counting favorites per photo

Any authenticated user can favorite any photo. `is_favorite` in API responses is computed per-requesting-user via this pivot. `favorites_count` is a `withCount('favoritedBy')`.

### 4.7 `personal_access_tokens` (Sanctum)

Standard Sanctum table **with one modification**: `tokenable_id` must be `CHAR(36)` (not `UNSIGNED BIGINT`) to match `users.id`.

### 4.8 `jobs` / `failed_jobs` / `job_batches`

Standard Laravel queue tables. Read by the Filament `QueueMonitor` widget. In production with SQS, `jobs` is unused; only `failed_jobs` and `job_batches` matter.

---

## 5. Eloquent relationships

```php
// Photo
public function user(): BelongsTo            // → User
public function album(): BelongsTo           // → Album
public function tags(): BelongsToMany        // → Tag (photo_tag pivot)
public function coverOf(): HasOne            // inverse of Album.coverPhoto

// Album
public function user(): BelongsTo            // → User
public function photos(): HasMany            // → Photo (album_id)
public function coverPhoto(): BelongsTo      // → Photo (cover_photo_id)

// Tag
public function photos(): BelongsToMany      // → Photo

// User
public function photos(): HasMany            // → Photo (user_id)
public function albums(): HasMany            // → Album (user_id)
public function tokens(): MorphMany          // Sanctum HasApiTokens
public function isAdmin(): bool              // attribute getter from is_admin column
```

**Cascade summary:**
- Delete `Photo` → `photo_tag` rows CASCADE; `albums.cover_photo_id` SET NULL; `PhotoObserver` deletes 4 files.
- Delete `Album` → `photos.album_id` SET NULL; photos survive.
- Delete `Tag` → `photo_tag` rows CASCADE; photos survive (admin-only operation).
- Delete `User` → all owned `photos` and `albums` CASCADE; tokens CASCADE via Sanctum morph.

---

## 6. API contracts

**Base URL:** `/api/v1`. **Content type:** JSON or `multipart/form-data` (uploads). **Auth:** Bearer token where required.

### 6.0 Conventions

- **Uniform envelope:** every success response wraps in `{ "data": ... }`. List endpoints add `links` and `meta`.
- **Cursor pagination** (UUID v7 keys are sortable): `meta.next_cursor`, `meta.prev_cursor`, no `total`.
- **Timestamps:** ISO-8601 UTC.
- **Rate limits:** `/auth/*` ≤ 10/min/IP; everything else ≤ 120/min/IP AND ≤ 300/min/user.
- **ETag** on `GET /photos/{id}` and `GET /albums/{id}`: `W/"<sha1(updated_at)>"`. Clients sending matching `If-None-Match` get `304`.
- **Errors:** universal envelope per §11.

### 6.1 Auth endpoints

#### `POST /auth/register`
- **Auth:** none · **Content-Type:** `application/json`
- **Body:** `{ name (2–255), email (unique), password (≥8, confirmed), password_confirmation }`
- **201 Created:**
  ```json
  { "data": { "token": "...", "token_type": "Bearer", "expires_at": "...", "user": {...} } }
  ```
- **Errors:** 422

#### `POST /auth/login`
- **Auth:** none · **Body:** `{ email, password, device_name? }`
- **200 OK:** same shape as register
- **Errors:** 401 (bad creds), 422

#### `POST /auth/logout`
- **Auth:** token · **Body:** empty
- **204 No Content** — revokes `currentAccessToken()`
- **Errors:** 401

#### `GET /auth/me`
- **Auth:** token
- **200 OK:** `{ "data": <UserData> }`
- **Errors:** 401

### 6.2 Photo endpoints

#### `GET /photos`
- **Auth:** none
- **Query parameters:**

  | Param | Type | Default | Rules |
  |---|---|---|---|
  | `search` | string | null | 1–100 chars; `MATCH(title, description) AGAINST (?)` |
  | `tags[]` | string[] (slugs) | [] | up to 10; AND logic; each must exist |
  | `album_id` | UUID | null | must exist |
  | `favorites` | `0`/`1` | `0` | — |
  | `sort` | enum | `created_at` | `created_at` / `title` / `favorites` |
  | `order` | enum | `desc` | `asc` / `desc` |
  | `per_page` | int | `24` | 1–100 |
  | `cursor` | opaque | null | from prior response |

- **200 OK:** pagination envelope; `data: PhotoData[]`; eager-loads `with(['album:id,name', 'tags:id,name,slug', 'user:id,name'])`.
- **Errors:** 422

#### `GET /photos/{photo}`
- Route-model-bound to `Photo $photo`.
- **Auth:** none
- **200 OK:** `{ "data": PhotoData }`
- **304 Not Modified** if `If-None-Match` matches the ETag.
- **Errors:** 404

#### `POST /photos` — single endpoint for 1–20 files
- **Auth:** token (`photos:write`)
- **Content-Type:** `multipart/form-data` (JSON body → 415)
- **Form fields:**

  | Field | Type | Required | Rules |
  |---|---|---|---|
  | `files[]` | File[] | yes | 1–20 items; each `image\|mimes:jpg,jpeg,png,webp\|max:10240` |
  | `titles[]` | string[] | no | per-file titles; default = filename without extension |
  | `description` | string | no | applied to all uploaded photos |
  | `album_id` | UUID | no | must exist + be owned by auth user |
  | `tags[]` | string[] (slugs) | no | up to 20; existing slugs only |
  | `new_tags[]` | string[] (names) | no | up to 20; created if missing |
  | `is_favorite` | `0`/`1` | no | default `0` |

- **Behavior** (wrapped in `DB::transaction`):
  1. Upsert any `new_tags[]` names → tag rows (slug auto-generated, collision-resolved).
  2. Strip GPS EXIF from each file via `ExifExtractor::stripGps`.
  3. `PhotoStorage::storeOriginal(...)` writes to `photos_private` disk.
  4. Insert `Photo` row per file with `user_id = auth()->id()`, `processing_status = 'pending'`.
  5. Attach merged tag set.
  6. Wrap one `ProcessPhoto` per photo in `Bus::batch(...)`.
- **202 Accepted** with `Location: /api/v1/photos/batch/{batch_id}` header:
  ```json
  {
    "data": {
      "batch_id": "uuid",
      "total": 3,
      "photos": [<PhotoData>, <PhotoData>, <PhotoData>]
    }
  }
  ```
- **Errors:** 403 (album not owned), 413, 415, 422

#### `PATCH /photos/{photo}`
- **Auth:** token (`photos:write`) + ownership policy
- **Content-Type:** `application/json`
- **Body** (any subset, ≥1 field):

  | Field | Type | Rules |
  |---|---|---|
  | `title` | string | 1–255 |
  | `description` | string\|null | 0–5000 |
  | `album_id` | UUID\|null | must exist + owned; null unassigns |
  | `tags[]` | string[] (slugs) | full replacement |
  | `new_tags[]` | string[] (names) | created if missing |
  | ~~`is_favorite`~~ | — | **REMOVED** — use `PUT/DELETE /photos/{id}/favorite` instead |

- Wrapped in `DB::transaction`.
- **200 OK:** `{ "data": PhotoData }`
- **Errors:** 403, 404, 422

#### `DELETE /photos/{photo}`
- **Auth:** token + ownership
- **204 No Content** — `PhotoObserver::deleted()` removes 4 files from disks.
- **Errors:** 403, 404

#### `PUT /photos/{photo}/favorite`
Mark as favorite for the requesting user. **Idempotent.** Any authenticated user can favorite any photo.
- **Auth:** token (any authenticated user — no ownership required)
- **Body:** empty
- **204 No Content** — inserts row in `favorites` pivot if not already present
- **Errors:** 404

#### `DELETE /photos/{photo}/favorite`
Unmark for the requesting user. **Idempotent.**
- **Auth:** token (any authenticated user — no ownership required)
- **Body:** empty
- **204 No Content** — removes row from `favorites` pivot if present
- **Errors:** 404

#### `GET /photos/batch/{batchId}`
Poll batch progress. **One** polling loop covers everything (no per-photo polling needed).
- **Auth:** token (must be batch creator)
- **200 OK:**
  ```json
  {
    "data": {
      "batch_id": "uuid",
      "total": 5, "pending": 1, "processed": 4, "failed": 0,
      "finished": false, "cancelled": false,
      "created_at": "...", "finished_at": null,
      "photos": [
        { "id": "...", "processing_status": "completed", "urls": { "thumbnail": "...", "medium": "...", "large": "..." } },
        ...
      ]
    }
  }
  ```
- **Errors:** 403, 404

### 6.3 Album endpoints

#### `GET /albums`
- **Auth:** none · **Query:** `sort` (`name_asc`/`newest`/`most_photos`), `per_page`, `cursor`
- **200 OK:** pagination envelope; eager-loads `with('coverPhoto:id,thumbnail_path,user_id')->withCount('photos')`
- **Errors:** 422

#### `GET /albums/{album}`
- **Auth:** none
- **200 OK / 304:** `{ "data": AlbumData }`
- **Errors:** 404

#### `POST /albums`
- **Auth:** token (`albums:write`)
- **Body:** `{ name (1–255, unique-per-user), description?, cover_photo_id? (must be owned) }`
- **201 Created:** `{ "data": AlbumData }`
- **Errors:** 422

#### `PATCH /albums/{album}`
- **Auth:** token + ownership policy
- **Body:** any subset of name/description/cover_photo_id
- **200 OK:** `{ "data": AlbumData }`
- **Errors:** 403, 404, 422

#### `DELETE /albums/{album}`
- **Auth:** token + ownership policy
- **204 No Content** — photos' `album_id` set to NULL via DB cascade; photos survive.
- **Errors:** 403, 404

### 6.4 Tag endpoints

#### `GET /tags`
- **Auth:** none · **Query:** `sort` (`count_desc`/`name_asc`)
- **200 OK:** `{ "data": TagData[] }` — backed by `Tag::withCount('photos')`. Not paginated.
- **Errors:** 422

Tag mutations are admin-only (Filament panel). No `POST /tags` / `PATCH /tags/{id}` / `DELETE /tags/{id}` in v1 API.

### 6.5 Health

#### `GET /health`
- **Auth:** none · No rate limit (uptime monitor friendly)
- **200 OK:** `{ "status": "ok", "storage": "ok", "queue": "ok" }`
- **503 Service Unavailable:** if any check fails; body lists the failure.

### 6.6 Resource shapes

**PhotoData:**
```json
{
  "id": "uuid", "title": "...", "description": "...", "filename": "...",
  "urls": {
    "thumbnail": "https://cdn.../thumbnails/abc.jpg",
    "medium":    "https://cdn.../medium/abc.jpg",
    "large":     "https://cdn.../large/abc.jpg",
    "original":  "https://cdn.../signed/.../originals/abc.jpg"  // owner only; signed; 5-min TTL
  },
  "width": 4000, "height": 3000,
  "file_size": 2500000, "mime_type": "image/jpeg",
  "is_favorite": true,       // relative to the requesting user (from favorites pivot)
  "favorites_count": 12,     // total across all users
  "exif": { "camera": "...", "iso": 400, "aperture": "f/2.8", "shutter": "1/250", "focal_length": "85mm", "taken_at": "..." },
  "processing_status": "completed", "processing_error": null,
  "album": { "id": "uuid", "name": "Travel" },
  "tags": [ { "id": "uuid", "name": "sunset", "slug": "sunset" } ],
  "owner": { "id": "uuid", "name": "Alex" },
  "created_at": "...", "updated_at": "..."
}
```

Nullability:
- `description`, `exif`, `processing_error`, `album` — `null` when unset.
- `urls.thumbnail`/`medium`/`large`, `width`, `height` — `null` until `processing_status === 'completed'`.
- `urls.original` — present only for the owner (or admin); else absent.
- `tags` — always an array.

**AlbumData:**
```json
{
  "id": "uuid", "name": "Travel", "description": "...",
  "cover_photo": { "id": "uuid", "urls": { "thumbnail": "..." } },
  "photos_count": 42,
  "owner": { "id": "uuid", "name": "Alex" },
  "created_at": "...", "updated_at": "..."
}
```

**TagData:**
```json
{ "id": "uuid", "name": "sunset", "slug": "sunset", "photos_count": 42 }
```

**UserData:**
```json
{ "id": "uuid", "name": "Alex", "email": "alex@example.com", "is_admin": false, "created_at": "..." }
```

### 6.7 Error matrix (per endpoint)

Universal codes (`401`, `429`, `500`) omitted; each endpoint also returns those when applicable.

| Endpoint | Codes |
|---|---|
| `POST /auth/register` | 201, 422 |
| `POST /auth/login` | 200, 401, 422 |
| `POST /auth/logout` | 204 |
| `GET /auth/me` | 200 |
| `GET /photos` | 200, 304, 422 |
| `GET /photos/{id}` | 200, 304, 404 |
| `POST /photos` | 202, 403, 413, 415, 422 |
| `PATCH /photos/{id}` | 200, 403, 404, 422 |
| `DELETE /photos/{id}` | 204, 403, 404 |
| `PUT /photos/{id}/favorite` | 204, 403, 404 |
| `DELETE /photos/{id}/favorite` | 204, 403, 404 |
| `GET /photos/batch/{id}` | 200, 403, 404 |
| `GET /albums` | 200, 422 |
| `GET /albums/{id}` | 200, 304, 404 |
| `POST /albums` | 201, 422 |
| `PATCH /albums/{id}` | 200, 403, 404, 422 |
| `DELETE /albums/{id}` | 204, 403, 404 |
| `GET /tags` | 200, 422 |
| `GET /health` | 200, 503 |

### 6.8 Backend conventions (apply everywhere)

1. **Route model binding** for every `{model}` path param. Controllers receive `Photo $photo`, never `string $id`.
2. **`$request->validated()`** only — never `$request->all()`.
3. **`DB::transaction(fn () => ...)`** wraps every multi-write operation.
4. **Explicit eager loading.** Every list query declares `with([...])` and `withCount([...])`.
5. **Authorization via policies.** Every mutating method calls `$this->authorize($action, $model)`.
6. **Sanctum abilities.** Mutating controllers also call `$request->user()->tokenCan('photos:write'|'albums:write')`.
7. **Domain events** for cross-cutting side effects (`PhotoUploaded`, etc.).
8. **No business logic in Filament resources.** Filament forms/tables call the same Actions and policies as the API.
9. **Actions** (single-purpose invokables in `app/Actions/`) own use cases. Controllers are thin coordinators that call Actions.

---

## 7. Filament admin

Panel mounted at `/admin` (path `admin`, primary color `#6366F1`, `web` guard).

### 7.1 PhotoResource
**Table columns:** thumbnail (ImageColumn 60×60), title (searchable, sortable), `album.name`, tags (badges), file_size (human bytes), `is_favorite` (ToggleColumn), `processing_status` (badge: pending=gray, processing=blue, completed=green, failed=red), created_at (since format).

**Filters:** album (SelectFilter), tags (multi-relationship), is_favorite (TernaryFilter), date range, processing_status.

**Header actions:** CreateAction.

**Row actions:** ViewAction, EditAction, DeleteAction, custom **ReprocessAction** (dispatches `ProcessPhoto` and resets `processing_attempts=0`, `status='pending'`).

**Bulk actions:** DeleteBulkAction, AssignToAlbumBulkAction, ToggleFavoriteBulkAction, ReprocessBulkAction.

**Form:** FileUpload on `original_path` (disk `photos_private`, image preview, max 10240 KB), title, description, album select with createOptionForm, tags multi-select with createOptionForm, is_favorite toggle.

### 7.2 AlbumResource
**Table:** cover_photo thumbnail, name (searchable), `photos_count`, created_at.

**Form:** name (unique-per-user, ignoreRecord), description, cover_photo_id (scoped to album's photos).

**Relation manager:** `PhotosRelationManager` with attach/detach.

### 7.3 TagResource
**Table:** name (searchable), slug (read-only), photos_count.

**Form:** name (unique, required) — `afterStateUpdated` sets slug via `Str::slug`. Slug field with prefix lock icon.

### 7.4 Widgets
- **StatsOverview** — 4 stats: Total Photos / Albums / Tags / Storage (sum of file_size, human-formatted).
- **RecentUploadsTable** — last 10 photos, polled every 10 s.
- **QueueMonitor** — Pending Jobs (jobs table), Failed Jobs (red when > 0), Photos Processing (status='processing'). Polled every 5 s.

### 7.5 Storage Management page
Custom `App\Filament\Pages\StorageManagement` (nav group "System"):
- Per-folder breakdown (originals / thumbnails / medium / large): size + file count.
- **Regenerate all thumbnails** button → dispatches `ProcessPhoto` for every photo, chunked in 100s.
- **Purge failed jobs** button → truncates `failed_jobs` (with confirmation).

---

## 8. Frontend components

### 8.0 Frontend conventions
1. **Strict TypeScript.** `tsconfig.json`: `"strict": true`, `"noUncheckedIndexedAccess": true`, `"noImplicitOverride": true`, `"exactOptionalPropertyTypes": true`. `any` is banned.
2. **Error boundaries** wrap every page and `PhotoLightbox`/`UploadModal`.
3. **Code splitting** at the route level (`React.lazy` + `<Suspense>`). Initial bundle target: ≤ 200 KB gzip.
4. **Server state in TanStack Query.** Mutations declare invalidations explicitly.
5. **Optimistic updates** for favorite mark/unmark.
6. **Image rendering rules:** `loading="lazy"`, `decoding="async"`, explicit `width`/`height`, `srcset` + `sizes`, `fetchpriority="high"` on lightbox.
7. **Focus management:** `react-focus-lock` in Modal + Lightbox.
8. **A11y:** semantic HTML, `aria-label` on icon buttons, alt text on every image; axe-core in Playwright.
9. **Reduced motion:** `prefers-reduced-motion` honored.
10. **No raw HTML injection.** ESLint rule `react/no-danger: error`. User text is plain text only in v1.
11. **Token cache:** read once at app start; held in module scope; refreshed on auth events.
12. **Polling cleanup:** every effect returns a cleanup that aborts.
13. **Singleton keyboard shortcuts** via `KeyboardShortcutsProvider` mounted in `App.tsx`.

### 8.1 App shell + routing

`App.tsx` wires `QueryClientProvider`, `BrowserRouter`, `KeyboardShortcutsProvider`, top-level `ErrorBoundary`, `Toaster`. Routes (all `React.lazy`):
- `/` → GalleryPage
- `/favorites` → FavoritesPage
- `/albums/:albumId` → AlbumPage
- `/login` → LoginPage
- `*` → NotFoundPage

Authenticated routes render inside `<Shell>` (Navbar + Sidebar + `<Outlet/>`).

### 8.2 Navbar
SearchInput (debounced 300 ms via `useDebounce`, writes `?search=`), SortDropdown, UploadButton, auth menu.

### 8.3 Sidebar
"All Photos", "Favorites" (count badge), Albums (`useAlbums` with photos_count), Tags (`useTags`, sorted by count, chips toggle `?tags[]=`).

### 8.4 MasonryGrid
CSS columns (`columns-2 md:columns-3 lg:columns-4 gap-4`), each child uses `break-inside-avoid`. `useInfiniteQuery` + IntersectionObserver sentinel.

### 8.5 PhotoCard
Per §8.0 rule 6: `<img loading="lazy" decoding="async" srcset={thumbnail+medium} sizes="(max-width: 768px) 50vw, 25vw">`. Hover overlay: title + heart icon (optimistic `PUT`/`DELETE /photos/{id}/favorite`). Click sets `?photo=:id`. Processing overlay shows spinner during pending/processing, red error icon on failed.

### 8.6 PhotoLightbox
Portal, focus-locked, `aria-modal="true"`. Renders `urls.large` with `fetchpriority="high"`. Owner-only "View full size" link uses `urls.original` (signed). Prefetches next/prev `large` URLs. Bottom bar inline-edits title/description/tags. Right drawer ExifPanel. Closes on backdrop / Escape / URL change; restores focus to the originating PhotoCard.

### 8.7 UploadModal + DropZone
Drag-drop + click-to-browse. Client validation: MIME ∈ {jpeg, png, webp}, size ≤ 10 MB. `useUpload` POSTs to `/photos` (1–20 files always go to the same endpoint). After 202, `useBatchPoll` polls `GET /photos/batch/{id}` every `data/polling.ts` `BATCH_MS` until `finished=true`, then invalidates `['photos']`.

### 8.8 Keyboard shortcuts

| Key | Action |
|---|---|
| `←` | Previous photo (lightbox open) |
| `→` | Next photo |
| `Escape` | Close lightbox/modal |
| `F` | Toggle favorite on current photo |
| `Delete` | Delete current photo (with confirm) |
| `/` | Focus search input |

All ignored when focus is in an input/textarea/[contenteditable] except `/`, which steals focus.

### 8.9 Data module
`src/data/copy.ts` holds every UI string. `src/data/polling.ts` holds intervals. `src/data/shortcuts.ts` defines key→action names. `src/data/nav.ts` defines navigation entries.

### 8.10 API client
`src/api/client.ts`: axios instance with `baseURL: '/api/v1'`. Request interceptor attaches `Authorization: Bearer <module-cached token>`. Response interceptor: 401 → clear token + redirect; 413 → toast; 422 → bubble to caller; 5xx → toast.

### 8.11 Vite config

```ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api':     { target: 'http://localhost:8000', changeOrigin: true },
      '/storage': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
});
```

### 8.12 Tailwind v4 setup (authoritative)

`src/index.css`:
```css
@import "tailwindcss";

@theme {
  --color-brand-500: oklch(0.65 0.2 270);
  --font-sans: "Inter", system-ui, sans-serif;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation: none !important; transition: none !important; }
}
```

**Forbidden:** `tailwind.config.js`, `postcss.config.js`, `autoprefixer`. Theme tokens go inside `@theme` only.

---

## 9. Service interfaces (DIP)

All three interfaces live in `app/Contracts/`. Concrete implementations bound in `App\Providers\AppServiceProvider::register()`. Tests bind doubles in `setUp()`.

### 9.1 `App\Contracts\ImageProcessor`

```php
namespace App\Contracts;

use App\Models\Photo;

interface ImageProcessor
{
    /**
     * Generate thumbnail (300px), medium (800px), large (1600px) JPEGs.
     * Reads $photo->original_path from the photos_private disk; writes to photos disk.
     * Updates $photo->{thumbnail_path,medium_path,large_path,width,height}; saves.
     * Auto-orients from EXIF; never upscales; strips metadata from output.
     */
    public function generate(Photo $photo): void;
}
```

Concrete: `App\Services\Imaging\InterventionImageProcessor`. Test double: `tests/Doubles/FakeImageProcessor.php`.

### 9.2 `App\Contracts\ExifExtractor`

```php
namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ExifExtractor
{
    /**
     * Returns sanitized EXIF (no GPS) from a local file path.
     * Returns [] on failure or missing EXIF.
     * Keys returned: camera, iso, aperture, shutter, focal_length, taken_at.
     */
    public function extract(string $absolutePath): array;

    /**
     * Returns a copy of $file with all GPS EXIF tags removed.
     * The returned UploadedFile is safe to persist as the public original-equivalent
     * (though originals still go to photos_private).
     */
    public function stripGps(UploadedFile $file): UploadedFile;
}
```

Concrete: `App\Services\Imaging\PhpExifExtractor`. Test double: `tests/Doubles/FakeExifExtractor.php`.

### 9.3 `App\Contracts\PhotoStorage`

Abstracts the two-disk model. Lets the application speak in domain terms (`storeOriginal`, `storeVariant`, `signedOriginalUrl`) without knowing whether it's local or S3, public or private.

```php
namespace App\Contracts;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;

interface PhotoStorage
{
    /** Persists the original on the private disk. Returns relative path. */
    public function storeOriginal(UploadedFile $file, string $photoId): string;

    /** Persists a generated variant (thumbnail|medium|large) on the public disk. Returns relative path. */
    public function storeVariant(string $photoId, string $variant, string $contents): string;

    /** Public URL for a variant on the photos disk. */
    public function publicVariantUrl(string $relativePath): string;

    /** Time-limited signed URL (5 min default) for the original on photos_private. */
    public function signedOriginalUrl(Photo $photo, int $ttlSeconds = 300): string;

    /** Deletes all 4 files associated with a photo, ignoring missing. */
    public function purge(Photo $photo): void;
}
```

Concrete: `App\Services\Storage\DiskPhotoStorage` (uses `Storage::disk('photos')` and `Storage::disk('photos_private')`). Test double: backed by `Storage::fake(...)`.

### 9.4 Bindings

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(ImageProcessor::class, InterventionImageProcessor::class);
    $this->app->bind(ExifExtractor::class, PhpExifExtractor::class);
    $this->app->bind(PhotoStorage::class, DiskPhotoStorage::class);
}
```

Consumers (`ProcessPhoto`, `UploadPhotosAction`, `PhotoController`, Filament forms) accept the interfaces via constructor or method injection — never `new ConcreteClass()`.

---

## 10. Authentication

### 10.1 Filament (admin)
Standard Laravel session auth via `web` guard. `AdminPanelProvider::authGuard('web')`. Seeded via `AdminUserSeeder` (email + password from `.env`).

### 10.2 React SPA — Sanctum **token mode**
- `config/sanctum.php` — token expiration **24 hours** (`SANCTUM_TOKEN_EXPIRATION=1440` minutes).
- `POST /auth/login` returns `{ data: { token, expires_at, user } }`.
- Frontend stores token in `localStorage` under `pgp_token`; held in module scope for hot-path access.
- Axios attaches `Authorization: Bearer <token>`.
- Public GETs need no auth; mutating routes are wrapped with `auth:sanctum`.
- `POST /auth/logout` revokes `currentAccessToken()`.

### 10.3 Token abilities

Issued at login matching the user's role:

| Ability | Granted to | Required for |
|---|---|---|
| `photos:write` | every authenticated user | photo POST/PATCH/DELETE, favorite mark/unmark |
| `albums:write` | every authenticated user | album POST/PATCH/DELETE |
| `admin` | users where `is_admin=true` | bypass ownership in policies; Filament |

```php
$user->createToken($deviceName, $user->isAdmin()
    ? ['photos:write', 'albums:write', 'admin']
    : ['photos:write', 'albums:write']
);
```

Controllers verify with `$request->user()->tokenCan('photos:write')` plus `$this->authorize($action, $model)`.

### 10.4 CORS
`config/cors.php`: `paths: ['api/*']`, `allowed_origins: [env('FRONTEND_URL')]` (no `*`), `supports_credentials: false`.

### 10.5 XSS / token storage trade-off
v1 chooses `localStorage` + Bearer tokens (XSS-vulnerable) over Sanctum stateful cookie mode (CSRF-complex), with these mitigations:
1. 24-hour token TTL.
2. Strict CSP in production: `default-src 'self'; img-src 'self' https://cdn.example.com data:; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'`.
3. ESLint rule `react/no-danger: error` (no raw HTML injection anywhere).
4. No user-submitted markdown; descriptions are plain text.
5. Token abilities limit damage even on theft.

---

## 11. Error handling

### 11.1 API error envelope

Every non-2xx response uses this shape:

| HTTP | Code | Body |
|---|---|---|
| 304 | `not_modified` | empty body (ETag matched) |
| 401 | `unauthenticated` | `{ "message": "Unauthenticated." }` |
| 403 | `forbidden` | `{ "message": "This action is unauthorized." }` |
| 404 | `not_found` | `{ "message": "Resource not found." }` |
| 413 | `payload_too_large` | `{ "message": "File exceeds 10 MB limit." }` |
| 415 | `unsupported_media_type` | `{ "message": "Only JPG, PNG, WebP supported." }` |
| 422 | `validation_failed` | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 429 | `rate_limited` | `{ "message": "Too many requests." }` |
| 500 | `server_error` | `{ "message": "Server error." }` (details only when `APP_DEBUG=true`) |
| 503 | `unavailable` | `{ "message": "...", "checks": { "storage": "...", "queue": "..." } }` |

Implemented in `bootstrap/app.php` exception handler with `$exceptions->shouldRenderJsonWhen(fn ($req, $e) => $req->is('api/*'))`. `App\Http\Middleware\ForceJsonResponse` ensures HTML never leaks on `/api/*`.

### 11.2 Per-scenario status codes

| Scenario | Code | Notes |
|---|---|---|
| Anonymous accessing public GET | 200 | — |
| Anonymous accessing protected route | 401 | — |
| Token expired / invalid | 401 | — |
| Authenticated but missing required ability | 403 | "This token does not have the required ability." |
| Authenticated, wrong owner | 403 | Policy denies |
| Resource not found | 404 | Route model binding 404 |
| Cache match (ETag) | 304 | Empty body |
| Multipart required, JSON sent | 415 | — |
| File MIME not allowed | 415 | — |
| File > 10 MB | 413 | Or 422 if Laravel rule trips first |
| Validation error | 422 | `errors` map populated |
| Rate limit exceeded | 429 | `Retry-After` header set |
| Server exception | 500 | Logged with stack trace |
| Health check failure | 503 | — |

### 11.3 Frontend error UX
- **Toasts (sonner):** mutation `onSuccess` / `onError`.
- **Inline validation** on 422 via React Hook Form `setError`.
- **Empty states** with CTA.
- **Error boundary fallback** with retry button (calls `resetErrorBoundary`).
- **Processing-failed photos** show a red badge overlay; admin reprocess available in Filament.

---

## 12. Image processing pipeline

### 12.1 Two-disk model
- `Storage::disk('photos_private')` — originals only. Local: `storage/app/photos-private/`. Production: private S3 bucket with Block Public Access ON. URLs only via `temporaryUrl(...)` with 5-min TTL.
- `Storage::disk('photos')` — sized variants only. Local: `storage/app/public/photos/` + `php artisan storage:link`. Production: public S3 bucket fronted by CloudFront.

### 12.2 Upload flow

1. `POST /photos` receives multipart form data (1–20 files).
2. `StorePhotosRequest` validates: `files|array|min:1|max:20`, each `image|mimes:jpg,jpeg,png,webp|max:10240`.
3. `UploadPhotosAction::__invoke` (wrapped in `DB::transaction`):
   - Upserts `new_tags[]` via `TagAssigner`.
   - For each file: `ExifExtractor::stripGps($file)` → `PhotoStorage::storeOriginal($sanitized, $uuid)`.
   - Inserts `Photo` rows with `user_id`, `processing_status='pending'`, file metadata.
   - Attaches merged tag set.
   - Wraps `ProcessPhoto::dispatch($photo)` per photo in `Bus::batch(...)`.
4. Returns `202 Accepted` with `Location: /api/v1/photos/batch/{batch_id}` header.
5. Fires `PhotoUploaded` event per photo (listeners: invalidate stats cache, future webhooks).

### 12.3 `ProcessPhoto` job

```php
final class ProcessPhoto implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly Photo $photo) {}

    public function handle(ImageProcessor $processor, ExifExtractor $exif): void
    {
        $this->photo->update(['processing_status' => ProcessingStatus::Processing]);
        $processor->generate($this->photo);  // writes 3 variants, sets width/height/paths
        $exifData = $exif->extract(/* ...local path of original... */);
        $this->photo->update([
            'exif' => $exifData,
            'processing_status' => ProcessingStatus::Completed,
        ]);
        event(new PhotoProcessed($this->photo));
    }

    public function failed(\Throwable $e): void
    {
        $this->photo->update([
            'processing_status' => ProcessingStatus::Failed,
            'processing_error' => $e->getMessage(),
        ]);
    }
}
```

**Sizes** (read from `config/photogallery.php`):

| Variant | Width | Quality | Format | EXIF |
|---|---|---|---|---|
| thumbnail | 300 px | 80 | JPEG | stripped |
| medium | 800 px | 85 | JPEG | stripped |
| large | 1600 px | 90 | JPEG | stripped |

Never upscale. Auto-orient from EXIF before stripping.

### 12.4 Batch tracking

`POST /photos` wraps N `ProcessPhoto` jobs in `Bus::batch(...)`. Returns `batch_id`. `GET /photos/batch/{id}` reads `Bus::findBatch($id)` and joins per-photo statuses (the `Photo` rows whose ids are in the batch).

### 12.5 Env switching

`config/filesystems.php` (excerpt):

```php
'disks' => [
    'photos' => [
        'driver' => env('PHOTOS_DRIVER', 'local') === 's3' ? 's3' : 'local',
        'root' => storage_path('app/public/photos'),
        'url' => env('APP_URL') . '/storage/photos',
        'bucket' => env('AWS_BUCKET_PUBLIC'),
        'visibility' => 'public',
    ],
    'photos_private' => [
        'driver' => env('PHOTOS_DRIVER', 'local') === 's3' ? 's3' : 'local',
        'root' => storage_path('app/photos-private'),
        'bucket' => env('AWS_BUCKET_PRIVATE'),
        'visibility' => 'private',
        'throw' => true,
    ],
],
```

`.env` (local dev):
```
QUEUE_CONNECTION=database
FILESYSTEM_DISK=photos
PHOTOS_DRIVER=local
PHOTOS_MAX_DIMENSION=8000
PHOTOS_MAX_FILE_SIZE_KB=10240
SANCTUM_TOKEN_EXPIRATION=1440
DB_PERSISTENT_CONNECTIONS=false
```

`.env.production.example`:
```
QUEUE_CONNECTION=sqs
SQS_KEY=...
SQS_SECRET=...
SQS_PREFIX=https://sqs.eu-west-1.amazonaws.com/...
SQS_QUEUE=photogallery-pro
SQS_REGION=eu-west-1

FILESYSTEM_DISK=photos
PHOTOS_DRIVER=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET_PUBLIC=photogallery-pro-prod
AWS_BUCKET_PRIVATE=photogallery-pro-prod-private
AWS_URL=https://cdn.example.com

SANCTUM_TOKEN_EXPIRATION=1440
DB_PERSISTENT_CONNECTIONS=false
```

### 12.6 Workers
- **Local:** `php artisan queue:work --queue=default --tries=3 --timeout=180 --memory=512`
- **Production:** `php artisan queue:work sqs --queue=photogallery-pro --tries=3 --timeout=180 --sleep=3 --memory=512` (under Supervisor)

`--timeout=180` accommodates `PHOTOS_MAX_DIMENSION=8000` source images on modest hardware.

`DB_PERSISTENT_CONNECTIONS=false` mandatory so a stale connection during long sleep doesn't survive into the next job.

**Failed-job hygiene:** `app/Console/Kernel.php` — `$schedule->command('queue:flush')->weekly()`.

### 12.7 Logging
Dedicated `processing` log channel in `config/logging.php`; `ProcessPhoto::failed()` writes to it. Production aggregates to CloudWatch (or Sentry if configured).

---

## Appendix A — `config/photogallery.php`

```php
return [
    'images' => [
        'max_dimension' => env('PHOTOS_MAX_DIMENSION', 8000),
        'max_file_size_kb' => env('PHOTOS_MAX_FILE_SIZE_KB', 10240),
        'variants' => [
            'thumbnail' => ['width' => 300, 'quality' => 80],
            'medium'    => ['width' => 800, 'quality' => 85],
            'large'     => ['width' => 1600, 'quality' => 90],
        ],
    ],
    'urls' => [
        'original_signed_ttl' => 300,  // seconds
    ],
    'rate_limits' => [
        'auth_per_ip_per_minute' => 10,
        'api_per_ip_per_minute' => 120,
        'api_per_user_per_minute' => 300,
    ],
];
```

`frontend/src/data/polling.ts`:
```ts
export const polling = {
  BATCH_MS: 1000,        // poll batch progress
  PHOTO_MS: 3000,        // legacy fallback if batch endpoint absent
  QUEUE_MS: 5000,        // admin queue widget
  RECENT_MS: 10000,      // admin recent uploads widget
  DEBOUNCE_MS: 300,      // search input
} as const;
```

---

**End of DESIGN.md**
