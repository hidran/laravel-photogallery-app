# PhotoGallery Pro — Tasks

> The execution layer. Atomic tasks (≤ 30 min each), grouped into 13 phases. Pair with `REQUIREMENTS.md` (acceptance criteria) and `DESIGN.md` (technical spec). Workflow rules in `CLAUDE.md`.

---

## How to use this file

1. **Pick a task** with status `[ ]` whose `Depends on` list is fully `[x]`.
2. **Read** the sections in `Reads`.
3. Do the work; verify against `Acceptance`.
4. **Commit** with the conventional message in the task's commit hint.
5. Mark the checkbox `[x]` in this file in the same commit.
6. If the task says `parallel: true` and you have an idle agent, dispatch a subagent for one of the tasks listed in `Parallel with`.

### Conventions

- **ID format:** `T001`–`T9xx`. Never reused.
- **Status:** `[ ]` pending · `[~]` in progress · `[x]` done · `[-]` skipped (with reason).
- **Owner roles:**
  - `backend-dev` — Laravel, Filament, queue, AWS
  - `frontend-dev` — React, Vite, Tailwind, Vitest, Playwright
  - `code-reviewer` — review-only; runs after a feature task
- **Reads** lists *minimum* sections; you may need more.
- **Acceptance** is the *definition of done* — testable, not aspirational.
- A task referencing `DESIGN.md §X` always also implies reading `CLAUDE.md` (rules) and `REQUIREMENTS.md` (acceptance criteria when relevant).

### Parallelization legend

- **`parallel: true`** — task can run in parallel with everything in `Parallel with`. Use the Agent tool to dispatch.
- **`magnet: true`** — task touches a magnet file (DESIGN.md §14.4). Run serially; broadcast before starting.

---

# Phase 1 — Backend foundation (models, migrations, seeders)

Depends on: Phase 11 (Git/PR setup) and Phase 9 (Hooks). Wave 0.

---

### T001 — Scaffold Laravel app + install dependencies
- **Status:** [x]
- **Owner:** backend-dev
- **Depends on:** —
- **Parallel with:** T002 (different working dir)
- **Reads:** DESIGN.md §2, CLAUDE.md "Tech stack"
- **Acceptance:**
  - `backend/` exists; `cd backend && php artisan --version` returns `Laravel Framework 13.x`.
  - `composer require` lines from CLAUDE.md ran without errors; `composer show` lists Filament, Sanctum, Intervention Image, flysystem-aws-s3-v3.
  - Dev deps include Pint, Rector, Pest, beyondcode/laravel-query-detector.
- **Commit:** `chore(backend): scaffold Laravel 13 with core dependencies`

### T002 — Scaffold React app + install dependencies
- **Status:** [x]
- **Owner:** frontend-dev
- **Depends on:** —
- **Parallel with:** T001
- **Reads:** DESIGN.md §2, §8.11, CLAUDE.md "Tech stack"
- **Acceptance:**
  - `frontend/` exists; `npm run dev` starts Vite at :5173.
  - `package.json` has every runtime + dev dep from CLAUDE.md.
- **Commit:** `chore(frontend): scaffold Vite + React 19 + TypeScript`

### T003 — Publish Sanctum + queue + batches migrations; fix tokenable_id type
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** —
- **Reads:** DESIGN.md §4.6, §4.7
- **Acceptance:**
  - Output of `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` plus `queue:table`, `queue:failed-table`, `queue:batches-table` is committed.
  - In the published Sanctum migration, `tokenable_id` column is `CHAR(36)` (not `UNSIGNED BIGINT`).
- **Commit:** `chore(backend): publish framework migrations; tokenable_id is CHAR(36)`

### T004 — Add `App\Concerns\HasUuidV7` trait
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** T005, T006
- **Reads:** DESIGN.md §4 conventions
- **Acceptance:**
  - `app/Concerns/HasUuidV7.php` exists; uses `HasUuids` and overrides `newUniqueId()` returning `(string) Str::uuid7()`.
  - Unit test asserts a model using the trait produces a v7 UUID (version nibble = 7).
- **Commit:** `feat(backend): add HasUuidV7 trait`

### T005 — Add `App\Enums\ProcessingStatus` and `TokenAbility` enums
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** T004, T006
- **Reads:** DESIGN.md §4.4 (status values), §10.3 (abilities)
- **Acceptance:**
  - `App\Enums\ProcessingStatus` is a backed string enum: `Pending`, `Processing`, `Completed`, `Failed`.
  - `App\Enums\TokenAbility` is a backed string enum: `PhotosWrite`, `AlbumsWrite`, `Admin`.
- **Commit:** `feat(backend): add ProcessingStatus and TokenAbility enums`

### T006 — Configure two `photos` disks in `config/filesystems.php`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** T004, T005
- **Reads:** DESIGN.md §12.5
- **Acceptance:**
  - `config/filesystems.php` defines `photos` (public) and `photos_private` (private) disks; both pick driver from `PHOTOS_DRIVER`.
  - `.env.example` declares `PHOTOS_DRIVER=local` and `FILESYSTEM_DISK=photos`.
  - `php artisan storage:link` runs without error.
- **Commit:** `chore(backend): configure photos and photos_private disks`

### T007 — Migration: `users` (with `is_admin`)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T003, T004
- **Parallel with:** —
- **Reads:** DESIGN.md §4.1
- **Acceptance:**
  - Migration creates the `users` table with all columns from §4.1 incl. `is_admin TINYINT(1) NOT NULL DEFAULT 0`.
  - `php artisan migrate:fresh` succeeds; `migrate:rollback` succeeds.
- **Commit:** `feat(db): users table with admin flag`

### T008 — Migration: `albums` (without cover_photo_id FK)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T007
- **Parallel with:** T009
- **Reads:** DESIGN.md §4.2
- **Acceptance:**
  - Composite UNIQUE (`user_id`, `name`) created.
  - `cover_photo_id` column exists but FK constraint deferred (added in T011).
- **Commit:** `feat(db): albums table with per-user unique name`

### T009 — Migration: `tags`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T007
- **Parallel with:** T008
- **Reads:** DESIGN.md §4.3
- **Acceptance:**
  - Both `name` and `slug` are UNIQUE indexes.
- **Commit:** `feat(db): tags table (global, unique name/slug)`

### T010 — Migration: `photos` (with FULLTEXT + composite indexes)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T008
- **Parallel with:** —
- **Reads:** DESIGN.md §4.4
- **Acceptance:**
  - Every column from §4.4 present with correct nullability/defaults.
  - All indexes from §4.4 created including FULLTEXT(`title`, `description`).
  - FK `user_id → users` ON DELETE CASCADE; FK `album_id → albums` ON DELETE SET NULL.
- **Commit:** `feat(db): photos table with composite indexes and fulltext`

### T011 — Migration: `add_cover_photo_fk_to_albums`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T010
- **Parallel with:** —
- **Reads:** DESIGN.md §4 migration order note
- **Acceptance:**
  - ALTER TABLE adds the FK on `albums.cover_photo_id → photos.id` ON DELETE SET NULL.
  - `migrate:fresh` succeeds end-to-end.
- **Commit:** `feat(db): resolve circular FK with cover_photo_id constraint`

### T012 — Migration: `photo_tag` pivot
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T011
- **Parallel with:** —
- **Reads:** DESIGN.md §4.5
- **Acceptance:**
  - Composite PRIMARY KEY (`photo_id`, `tag_id`).
  - Both FKs CASCADE both directions.
- **Commit:** `feat(db): photo_tag pivot with composite PK`

### T013 — Photo, Album, Tag, User Eloquent models
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T012, T004, T005
- **Parallel with:** —
- **Reads:** DESIGN.md §5
- **Acceptance:**
  - All four models exist with `HasUuidV7` (where appropriate; `User` keeps default ID strategy via the trait).
  - Casts: `processing_status` → enum, `is_favorite` → bool, `exif` → array, `is_admin` → bool, `width`/`height` → int.
  - All relationships from §5 implemented.
  - `User::isAdmin()` returns `bool`.
- **Commit:** `feat(backend): Eloquent models with relationships`

### T014 — `PhotoObserver` deletes 4 files on photo delete
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013, T006
- **Parallel with:** —
- **Reads:** DESIGN.md §3.2 (Observer location), §5 (cascade rules)
- **Acceptance:**
  - `App\Observers\PhotoObserver::deleted()` removes `original_path` from `photos_private` and 3 sized variants from `photos`. Guards against `null` paths.
  - Registered in `AppServiceProvider::boot`.
  - Feature test: deleting a Photo removes the matching files from `Storage::fake(...)`.
- **Commit:** `feat(backend): PhotoObserver removes files on delete`

### T015 — Factories for Photo, Album, Tag, User
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T016
- **Reads:** DESIGN.md §3.2 (factory paths)
- **Acceptance:**
  - Each factory generates a valid row using sensible defaults (Faker for strings, fixture path for `original_path`).
  - `Photo::factory()->create()` returns a model with a valid `user_id` and random `processing_status`.
- **Commit:** `feat(backend): model factories`

### T016 — `AdminUserSeeder` + `DatabaseSeeder` wiring
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T015
- **Reads:** DESIGN.md §3.2, §10.1
- **Acceptance:**
  - `AdminUserSeeder` creates a single user from `ADMIN_EMAIL`/`ADMIN_PASSWORD` env, with `is_admin=true`.
  - `DatabaseSeeder` calls `AdminUserSeeder`.
  - `php artisan migrate:fresh --seed` exits 0.
- **Commit:** `feat(backend): seed admin user from env`

### T017 — `config/photogallery.php` + `.env.example` augmentation
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** T004, T005, T006
- **Reads:** DESIGN.md §12, Appendix A
- **Acceptance:**
  - `config/photogallery.php` matches DESIGN.md Appendix A (variants, ttl, rate limits).
  - `.env.example` declares every PHOTO_*, SANCTUM_*, ADMIN_*, AWS_*, SQS_* var noted in DESIGN.md §12.5.
- **Commit:** `chore(backend): photogallery config and env example`

### T018 — Phase 1 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T001–T017
- **Parallel with:** —
- **Reads:** All Phase 1 commits
- **Acceptance:**
  - `php artisan migrate:fresh --seed` and `migrate:rollback` both succeed.
  - `php artisan tinker` can `App\Models\Photo::factory()->create()` and the row has UUID v7 `id` + valid relations.
  - Pint clean (`./vendor/bin/pint --test`).
  - No Eloquent N+1 in factory paths.
- **Commit:** `chore: Phase 1 review (no changes)` (or fixes if needed)

---

# Phase 2 — API layer (controllers, routes, resources, requests, policies)

Depends on Phase 1 complete. Wave 1 — Track A.

---

### T019 — `ForceJsonResponse` middleware
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T018
- **Parallel with:** T020
- **Reads:** DESIGN.md §3.2, §11.1
- **Acceptance:**
  - Middleware sets `Accept: application/json` on inbound `/api/*` requests.
  - Registered in `bootstrap/app.php` for the `api` group.
- **Commit:** `feat(api): force JSON middleware on /api/*`

### T020 — Custom exception handler with universal envelope
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T018
- **Parallel with:** T019
- **Reads:** DESIGN.md §11.1, §11.2
- **Magnet:** `bootstrap/app.php`
- **Acceptance:**
  - 401/403/404/413/415/422/429/500/503 all emit the documented envelope on `/api/*`.
  - `$exceptions->shouldRenderJsonWhen(fn ($req) => $req->is('api/*'))`.
- **Commit:** `feat(api): universal error envelope per DESIGN.md §11.1`

### T021 — `App\Http\Middleware\ETag`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T019
- **Parallel with:** T022
- **Reads:** DESIGN.md §6.0 (ETag rule)
- **Acceptance:**
  - Wraps single-resource GETs; emits `ETag: W/"<sha1(updated_at)>"`.
  - Returns 304 with empty body on `If-None-Match` match.
- **Commit:** `feat(api): ETag middleware for single-resource GETs`

### T022 — `PhotoPolicy` + `AlbumPolicy`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T021
- **Reads:** DESIGN.md §10.3, §6.4 (auth model)
- **Acceptance:**
  - Both policies define `update`, `delete`, `view` returning ownership ∨ admin.
  - Registered in `AuthServiceProvider`.
- **Commit:** `feat(backend): photo and album policies (ownership + admin)`

### T023 — Form requests: `RegisterRequest`, `LoginRequest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T024, T025, T026
- **Reads:** DESIGN.md §6.1
- **Acceptance:**
  - `RegisterRequest` rules: name 2–255, email valid+unique, password ≥8 confirmed.
  - `LoginRequest` rules: email valid, password required, optional device_name.
  - Per-rule unit tests in Pest.
- **Commit:** `feat(api): auth form requests`

### T024 — Form requests: `IndexPhotosRequest`, `StorePhotosRequest`, `UpdatePhotoRequest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T023, T025, T026
- **Reads:** DESIGN.md §6.2
- **Acceptance:**
  - `IndexPhotosRequest`: search/tags[]/album_id/favorites/sort/order/per_page/cursor rules per §6.2.
  - `StorePhotosRequest`: `files|array|min:1|max:20`, each `image|mimes:jpg,jpeg,png,webp|max:10240`; titles[] optional; album_id optional+exists; tags[]/new_tags[] rules.
  - `UpdatePhotoRequest`: subset of fields, ≥1 required.
- **Commit:** `feat(api): photo form requests`

### T025 — Form requests: `StoreAlbumRequest`, `UpdateAlbumRequest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T023, T024, T026
- **Reads:** DESIGN.md §6.3
- **Acceptance:**
  - Per-user uniqueness on `name` enforced via Rule::unique scoped to `user_id`.
  - Cover photo must exist and belong to authed user.
- **Commit:** `feat(api): album form requests`

### T026 — `PhotoData`, `AlbumData`, `TagData`, `UserData` resources
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T023, T024, T025
- **Reads:** DESIGN.md §6.6
- **Acceptance:**
  - Output exactly matches §6.6 shape and nullability rules.
  - `PhotoData::with` constant declares eager loads: `['album:id,name', 'tags:id,name,slug', 'user:id,name']`.
  - `urls.original` returned only when viewer is owner or admin.
- **Commit:** `feat(api): API resources (PhotoData/AlbumData/TagData/UserData)`

### T027 — `App\Queries\PhotoQuery` (composable filters/sorts)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T028
- **Reads:** DESIGN.md §6.2 (query params)
- **Acceptance:**
  - Methods `withSearch`, `withTags`, `withAlbum`, `withFavorites`, `applySort($sort,$order)`, `paginate($cursor,$perPage)`.
  - Unit tests covering each filter combo.
- **Commit:** `feat(backend): PhotoQuery composable builder`

### T028 — `App\Services\TagAssigner::syncByNames`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T027
- **Reads:** DESIGN.md §6.4 conventions, REQUIREMENTS §F7
- **Acceptance:**
  - `syncByNames(Photo $photo, array $names): void` upserts tags by name (slug auto-generated, collisions resolved with `-2`/`-3`/...) and replaces the photo's tag set.
  - Unit test covers create/reuse/collision.
- **Commit:** `feat(backend): TagAssigner service`

### T029 — `App\Actions\Photo\UploadPhotosAction`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T024, T026, T028, T032 (PhotoStorage from Phase 6, but use a stub here)
- **Parallel with:** —
- **Reads:** DESIGN.md §12.2 (upload flow), §6.2 (POST /photos)
- **Acceptance:**
  - Wraps the entire flow in `DB::transaction`.
  - Returns `{ batch_id, total, photos }` array.
  - Fires `PhotoUploaded` event per photo.
- **Note:** dispatches `ProcessPhoto` jobs — actual job impl arrives in Phase 6, but the Action wires `Bus::batch([new ProcessPhoto($photo), ...])` now.
- **Commit:** `feat(backend): UploadPhotosAction`

### T030 — `AuthController` (register, login, logout, me)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T020, T023, T026
- **Parallel with:** T031, T034, T035, T036
- **Reads:** DESIGN.md §6.1, §10
- **Acceptance:**
  - Endpoints match §6.1 shapes; 24h `expires_at` on token; `tokenCan` abilities issued by role.
  - Pest tests for every documented status code on every endpoint.
- **Commit:** `feat(api): AuthController with abilities`

### T031 — `PhotoController@index` and `@show`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T020, T024, T026, T027
- **Parallel with:** T030, T034, T035, T036
- **Reads:** DESIGN.md §6.2
- **Acceptance:**
  - Index uses `PhotoQuery`; cursor pagination; eager loads; ≤2 SQL queries (n+1 detector enforced).
  - Show: 200 / 304 / 404 covered.
- **Commit:** `feat(api): GET /photos and /photos/{id}`

### T032 — `PhotoController@store` (delegates to `UploadPhotosAction`)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T029
- **Parallel with:** —
- **Reads:** DESIGN.md §6.2 (POST /photos)
- **Acceptance:**
  - 202 with `Location` header; 415/413/422/403 covered.
- **Commit:** `feat(api): POST /photos delegates to UploadPhotosAction`

### T033 — `PhotoController@update`, `@destroy`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T022, T024, T026
- **Parallel with:** T034, T035, T036
- **Reads:** DESIGN.md §6.2
- **Acceptance:**
  - PATCH wraps in `DB::transaction`; calls `TagAssigner::syncByNames` when `tags[]`/`new_tags[]` present.
  - DELETE returns 204; 403 for non-owner; PhotoObserver fires.
- **Commit:** `feat(api): PATCH/DELETE /photos/{id}`

### T034 — Favorite endpoints: `PUT` and `DELETE /photos/{id}/favorite`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T022
- **Parallel with:** T030, T031, T035, T036
- **Reads:** DESIGN.md §6.2 (favorite)
- **Acceptance:**
  - Both endpoints idempotent: 204 on first and subsequent calls.
  - 403 for non-owner.
- **Commit:** `feat(api): idempotent PUT/DELETE favorite endpoints`

### T035 — `BatchController@show` for `GET /photos/batch/{id}`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T029
- **Parallel with:** T030, T031, T034, T036
- **Reads:** DESIGN.md §6.2 (batch progress shape)
- **Acceptance:**
  - Combines `Bus::findBatch($id)` with the matching `Photo` rows; returns the full shape from §6.2.
  - 403 if requester is not the batch creator.
- **Commit:** `feat(api): GET /photos/batch/{id}`

### T036 — `AlbumController` (CRUD)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T022, T025, T026
- **Parallel with:** T030, T031, T034, T035
- **Reads:** DESIGN.md §6.3
- **Acceptance:**
  - `index` uses cursor pagination + `withCount('photos')` + eager-loaded coverPhoto.
  - All five endpoints covered with status codes from §6.7.
- **Commit:** `feat(api): AlbumController CRUD`

### T037 — `TagController@index`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T026
- **Parallel with:** T038
- **Reads:** DESIGN.md §6.4
- **Acceptance:**
  - Returns `Tag::query()->withCount('photos')->orderBy(...)` — single SQL query.
  - `sort=count_desc|name_asc`.
- **Commit:** `feat(api): GET /tags`

### T038 — `HealthController@index`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T020
- **Parallel with:** T037
- **Reads:** DESIGN.md §6.5
- **Acceptance:**
  - Probes storage (write/read/delete a marker) and queue (peek `failed_jobs`).
  - 200 on success; 503 with `checks` map on failure.
- **Commit:** `feat(api): GET /health endpoint`

### T039 — Routes + middleware groups + rate limiters
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T030–T038
- **Parallel with:** —
- **Magnet:** `routes/api.php`, `bootstrap/app.php`
- **Reads:** DESIGN.md §6.0 (rate limits), §6.1–§6.5
- **Acceptance:**
  - `routes/api.php` declares every endpoint under `/v1` prefix.
  - Auth routes throttled `10,1` per IP; everything else `120,1` per IP and `300,1` per user.
  - Mutating routes wrapped in `auth:sanctum`.
- **Commit:** `feat(api): route definitions with rate limits`

### T040 — Pest tests: `AuthTest`, `AuthorizationTest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T030
- **Parallel with:** T041, T042, T043, T044
- **Reads:** REQUIREMENTS §AC-F9, DESIGN.md §6.7
- **Acceptance:**
  - Every Auth endpoint covered with happy + 401/422 paths.
  - `AuthorizationTest` iterates every mutating endpoint asserting 401 without token, 403 cross-user.
- **Commit:** `test(api): auth and authorization sweep`

### T041 — Pest tests: `PhotoIndexTest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T031
- **Parallel with:** T040, T042, T043, T044
- **Reads:** REQUIREMENTS §AC-F2, DESIGN.md §6.2
- **Acceptance:**
  - Datasets cover: search-only, tags-AND, album, favorites, each sort/order pair, cursor pagination round-trip.
- **Commit:** `test(api): GET /photos coverage`

### T042 — Pest tests: `PhotoCrudTest`, `PhotoUploadTest`, `PhotoFavoriteTest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T032, T033, T034
- **Parallel with:** T040, T041, T043, T044
- **Reads:** REQUIREMENTS §AC-F3, §AC-F4, §AC-F8, DESIGN.md §6.2
- **Acceptance:**
  - Upload tests cover: 415 (JSON), 413 (oversize), 422 (no files / >20 / unknown album), 202 happy with batch_id.
  - Favorite tests prove idempotency.
- **Commit:** `test(api): photo CRUD, upload, favorite`

### T043 — Pest tests: `BatchProgressTest`, `AlbumCrudTest`, `TagIndexTest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T035, T036, T037
- **Parallel with:** T040, T041, T042, T044
- **Reads:** DESIGN.md §6.2, §6.3, §6.4
- **Acceptance:**
  - Batch progress test asserts the bundled per-photo statuses.
  - Album test verifies per-user UNIQUE.
- **Commit:** `test(api): batch, albums, tags`

### T044 — Pest test: `HealthTest`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T038
- **Parallel with:** T040, T041, T042, T043
- **Reads:** DESIGN.md §6.5
- **Acceptance:**
  - Healthy stack returns 200.
  - With queue connection broken (mock), returns 503 with `checks.queue=error`.
- **Commit:** `test(api): health endpoint`

### T045 — Phase 2 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T040–T044
- **Parallel with:** —
- **Acceptance:**
  - `php artisan test --testsuite=Feature` green.
  - `php artisan test --filter=N+1` (or QueryDetector listener) flags zero violations.
  - Pint clean.
- **Commit:** `chore: Phase 2 review`

---

# Phase 3 — Filament admin (resources, widgets, dashboard)

Wave 1 — Track B. Parallel with Phase 2.

---

### T046 — Install Filament panel + brand
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T018
- **Parallel with:** T030 (different track)
- **Magnet:** `app/Providers/Filament/AdminPanelProvider.php`
- **Reads:** DESIGN.md §7
- **Acceptance:**
  - `php artisan filament:install --panels` ran; panel mounted at `/admin`, `web` guard, brand "PhotoGallery Pro Admin", primary color `#6366F1`.
  - Login as `AdminUserSeeder` user works.
- **Commit:** `chore(filament): install admin panel`

### T047 — `PhotoResource` (table)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T046
- **Parallel with:** T049, T051
- **Reads:** DESIGN.md §7.1
- **Acceptance:**
  - Table columns match §7.1 incl. processing_status badge with the documented colors.
  - Filters: album, tags (multiple), is_favorite (Ternary), date range, processing_status.
- **Commit:** `feat(filament): PhotoResource table + filters`

### T048 — `PhotoResource` (form + reprocess action)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T047
- **Parallel with:** T049, T051
- **Reads:** DESIGN.md §7.1, §12.3
- **Acceptance:**
  - Form fields per §7.1; FileUpload uses disk `photos_private` directory `originals`.
  - Custom `ReprocessAction` dispatches `ProcessPhoto::dispatch($record)` and resets status/attempts; success notification shown.
  - Bulk actions: Delete, AssignToAlbum, ToggleFavorite, Reprocess.
- **Commit:** `feat(filament): PhotoResource form, actions, bulk actions`

### T049 — `AlbumResource`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T046
- **Parallel with:** T047, T051
- **Reads:** DESIGN.md §7.2
- **Acceptance:**
  - Table + form per §7.2.
  - `PhotosRelationManager` works with attach/detach.
- **Commit:** `feat(filament): AlbumResource + relation manager`

### T050 — `TagResource`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T046
- **Parallel with:** T049
- **Reads:** DESIGN.md §7.3
- **Acceptance:**
  - Form auto-fills slug via `Str::slug` on `afterStateUpdated`.
  - photos_count column shows correct counts.
- **Commit:** `feat(filament): TagResource`

### T051 — Widgets: StatsOverview, RecentUploadsTable, QueueMonitor
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T046
- **Parallel with:** T047, T049
- **Magnet:** `AdminPanelProvider` (registration)
- **Reads:** DESIGN.md §7.4
- **Acceptance:**
  - Three widgets implemented and registered. QueueMonitor polls 5 s; RecentUploads polls 10 s.
- **Commit:** `feat(filament): dashboard widgets`

### T052 — `StorageManagement` page
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T046, T051
- **Parallel with:** —
- **Reads:** DESIGN.md §7.5
- **Acceptance:**
  - Per-folder breakdown displayed.
  - Buttons trigger their actions; confirmation modal on Purge.
- **Commit:** `feat(filament): storage management page`

### T053 — Filament smoke tests
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T047–T052
- **Parallel with:** —
- **Reads:** DESIGN.md §15.2
- **Acceptance:**
  - `livewire(ListPhotos::class)->assertSuccessful()` and similar for Albums, Tags.
  - Widgets render without errors.
- **Commit:** `test(filament): admin smoke tests`

### T054 — Phase 3 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T053
- **Acceptance:**
  - Manual: log in to /admin, verify every resource renders, dashboard shows stats.
- **Commit:** `chore: Phase 3 review`

---

# Phase 4 — React frontend setup (scaffold, Tailwind v4, API service)

Wave 1 — Track C. Parallel with Phases 2 + 3.

---

### T055 — Vite config + Tailwind v4 install
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T002
- **Parallel with:** T056, T057
- **Magnet:** `vite.config.ts`, `src/index.css`
- **Reads:** DESIGN.md §8.11, §8.12
- **Acceptance:**
  - `vite.config.ts` exactly matches §8.11.
  - `src/index.css` matches §8.12 incl. `prefers-reduced-motion` block.
  - **No** `tailwind.config.js`, `postcss.config.js`, or `autoprefixer`.
- **Commit:** `chore(frontend): Vite + Tailwind v4 setup`

### T056 — TypeScript strict + ESLint + Prettier
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T002
- **Parallel with:** T055, T057
- **Reads:** DESIGN.md §8.0, CLAUDE.md "Code style"
- **Acceptance:**
  - `tsconfig.json` enables strict, noUncheckedIndexedAccess, noImplicitOverride, exactOptionalPropertyTypes.
  - ESLint runs clean on a fresh tree.
  - Prettier config matches CLAUDE.md.
- **Commit:** `chore(frontend): strict TS + ESLint + Prettier`

### T057 — Type definitions in `src/types/`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T056
- **Parallel with:** T055, T058
- **Reads:** DESIGN.md §6.6
- **Acceptance:**
  - `photo.ts`, `album.ts`, `tag.ts`, `api.ts` exist with types matching §6.6 plus `PaginatedResponse<T>` and `ApiError`.
- **Commit:** `feat(frontend): API type definitions`

### T058 — `src/api/client.ts` + interceptors
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T057
- **Parallel with:** T057, T059
- **Reads:** DESIGN.md §8.10
- **Acceptance:**
  - Axios instance, baseURL `/api/v1`.
  - Module-scoped token cache (read once at module load, refreshed via `auth:login`/`auth:logout` events).
  - Interceptors per §8.10.
- **Commit:** `feat(frontend): axios client with interceptors`

### T059 — Typed API wrappers (`src/api/{photos,albums,tags,batch,auth}.ts`)
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T058
- **Parallel with:** T058
- **Reads:** DESIGN.md §6
- **Acceptance:**
  - Every endpoint in §6 has a typed wrapper.
  - Unit tests stub the client and verify URL/method/params for each wrapper.
- **Commit:** `feat(frontend): typed API wrappers`

### T060 — `lib/queryClient.ts` + Provider tree in `App.tsx`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T058
- **Parallel with:** T061
- **Magnet:** `App.tsx`, `main.tsx`
- **Reads:** DESIGN.md §8.0, §8.1
- **Acceptance:**
  - QueryClient configured: `staleTime: 30_000`, `retry: 1`.
  - `App.tsx` wires QueryClientProvider, BrowserRouter, KeyboardShortcutsProvider, top-level ErrorBoundary, sonner Toaster.
  - Routes are React.lazy with Suspense fallback.
- **Commit:** `feat(frontend): provider tree and lazy routing`

### T061 — `src/data/copy.ts`, `nav.ts`, `polling.ts`, `shortcuts.ts`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T002
- **Parallel with:** T060
- **Reads:** DESIGN.md §8.9, REQUIREMENTS §3 user stories
- **Acceptance:**
  - `copy.ts` exports every UI string referenced by REQUIREMENTS §3 (gallery, upload, lightbox, auth, errors).
  - `polling.ts` matches Appendix A.
- **Commit:** `feat(frontend): centralized data modules`

### T062 — Phase 4 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T055–T061
- **Acceptance:**
  - `npm run build` succeeds, zero TS errors.
  - `curl -sI http://localhost:5173/api/v1/photos` proxies to Laravel.
- **Commit:** `chore: Phase 4 review`

---

# Phase 5 — React components (grid, lightbox, upload, sidebar, keyboard)

Depends on Phase 4. **Heavily parallelizable** — 4 sub-agents (a/b/c/d).

---

### T063 — `ErrorBoundary` + `Modal` + common components
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T062
- **Parallel with:** T064, T065, T066
- **Reads:** DESIGN.md §8.0 rules 2 + 7
- **Acceptance:**
  - `ErrorBoundary` renders fallback with retry button + sonner toast.
  - `Modal` uses `react-focus-lock`, `aria-modal="true"`, restores focus on close.
- **Commit:** `feat(frontend): ErrorBoundary + Modal + common components`

### T064 — Layout: `Shell`, `Navbar`, `Sidebar`
- **Status:** [ ]
- **Owner:** frontend-dev (Agent 5a)
- **Depends on:** T060, T061, T063
- **Parallel with:** T065, T066, T067
- **Reads:** DESIGN.md §8.1–§8.3
- **Acceptance:**
  - Shell renders Navbar + Sidebar + `<Outlet />` with route ErrorBoundary.
  - Navbar SearchInput debounces 300 ms, writes `?search=`.
  - Sidebar uses `useAlbums` + `useTags` (still TanStack Query against real or mocked API).
- **Commit:** `feat(frontend): app shell + navbar + sidebar`

### T065 — Hooks: `useDebounce`, `useKeyboardShortcuts`, `KeyboardShortcutsProvider`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T060
- **Parallel with:** T064, T066, T067
- **Reads:** DESIGN.md §8.0 rule 13, §8.8
- **Acceptance:**
  - Provider singleton; consumer hook is declarative.
  - Vitest covers debounce and the input-focused-skip-except-`/` rule.
- **Commit:** `feat(frontend): keyboard shortcuts and useDebounce`

### T066 — Server-state hooks: `useAuth`, `usePhotos`, `useAlbums`, `useTags`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T058, T059
- **Parallel with:** T064, T065, T067
- **Reads:** DESIGN.md §8.0 rule 4
- **Acceptance:**
  - `usePhotos` is `useInfiniteQuery` with cursor pagination.
  - Mutation hooks declare `invalidateQueries` explicitly.
  - Optimistic update for favorite toggle (`onMutate` flips cache).
- **Commit:** `feat(frontend): TanStack Query hooks`

### T067 — Gallery: `MasonryGrid`, `PhotoCard`, `ProcessingOverlay`
- **Status:** [ ]
- **Owner:** frontend-dev (Agent 5b)
- **Depends on:** T063, T066
- **Parallel with:** T064, T065, T068, T069
- **Reads:** DESIGN.md §8.4, §8.5
- **Acceptance:**
  - `<img>` follows §8.0 rule 6 exactly (srcset, sizes, loading, decoding, width/height).
  - IntersectionObserver sentinel triggers `fetchNextPage`.
  - Optimistic favorite via mutation hook.
  - Vitest covers PhotoCard rendering with seed data.
- **Commit:** `feat(frontend): masonry grid + photo card + processing overlay`

### T068 — Lightbox: `PhotoLightbox`, `ExifPanel`
- **Status:** [ ]
- **Owner:** frontend-dev (Agent 5c)
- **Depends on:** T063, T065, T067
- **Parallel with:** T067, T069
- **Reads:** DESIGN.md §8.6, REQUIREMENTS §AC-F3
- **Acceptance:**
  - Focus trap + restore on close.
  - Arrow keys cycle; Escape closes; F toggles favorite; Delete confirms then deletes.
  - Prefetches `urls.large` of next/prev.
  - Vitest covers keyboard cycling.
- **Commit:** `feat(frontend): lightbox with keyboard nav and EXIF panel`

### T069 — Upload: `UploadModal`, `DropZone`, `UploadProgressList`, `useUpload`, `useBatchPoll`
- **Status:** [ ]
- **Owner:** frontend-dev (Agent 5d)
- **Depends on:** T063, T066
- **Parallel with:** T067, T068
- **Reads:** DESIGN.md §8.7, §6.2 (POST /photos), REQUIREMENTS §AC-F4
- **Acceptance:**
  - Client-side validates MIME + size **before** any network.
  - Single endpoint always (`POST /photos` accepting 1–20 files).
  - `useBatchPoll` polls `GET /photos/batch/{id}` once per `BATCH_MS`, stops on `finished=true`, invalidates `['photos']` once.
  - Vitest covers oversize-rejected and invalid-mime-rejected.
- **Commit:** `feat(frontend): upload modal with batch polling`

### T070 — Pages: `GalleryPage`, `FavoritesPage`, `AlbumPage`, `LoginPage`, `NotFoundPage`
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T064, T067, T068
- **Parallel with:** T069
- **Reads:** REQUIREMENTS §3
- **Acceptance:**
  - Each page reads filters from URL search params and feeds them into `usePhotos`.
  - LoginPage uses `useAuth.login`; redirects to intended route.
  - NotFoundPage renders empty state.
- **Commit:** `feat(frontend): page components wired to hooks`

### T071 — Phase 5 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T063–T070
- **Acceptance:**
  - `npm test -- --run` green.
  - Manual: log in, upload a photo, verify it appears, open lightbox, navigate with arrows.
  - Initial bundle ≤ 200 KB gzip via `vite-bundle-visualizer`.
- **Commit:** `chore: Phase 5 review`

---

# Phase 6 — Image processing pipeline (job, queue, batch, processing UI)

Depends on Phase 1 + Phase 2 (T032). Wave 1 — Track A continuation.

---

### T072 — `App\Contracts\ImageProcessor` + `ExifExtractor` + `PhotoStorage` interfaces
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T073
- **Reads:** DESIGN.md §9
- **Acceptance:**
  - Three interfaces created with exact signatures from §9.
- **Commit:** `feat(backend): service contracts (ImageProcessor, ExifExtractor, PhotoStorage)`

### T073 — `App\Services\Storage\DiskPhotoStorage` (concrete)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T072, T006
- **Parallel with:** T074, T075
- **Reads:** DESIGN.md §9.3, §12.5
- **Acceptance:**
  - Implements `PhotoStorage` against `Storage::disk('photos')` + `disk('photos_private')`.
  - `signedOriginalUrl` uses `temporaryUrl(...)` with config TTL (300 s).
  - `purge` ignores missing files.
  - Unit tests with `Storage::fake()`.
- **Commit:** `feat(backend): DiskPhotoStorage implementation`

### T074 — `App\Services\Imaging\PhpExifExtractor`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T072
- **Parallel with:** T073, T075
- **Reads:** DESIGN.md §9.2
- **Acceptance:**
  - `extract` returns the documented keys; never includes `GPSLatitude`/`GPSLongitude`/`GPSAltitude`.
  - `stripGps` returns a copy with GPS tags removed.
  - Unit test reads a fixture image with embedded GPS, asserts strip works.
- **Commit:** `feat(backend): PhpExifExtractor with GPS stripping`

### T075 — `App\Services\Imaging\InterventionImageProcessor`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T072
- **Parallel with:** T073, T074
- **Reads:** DESIGN.md §9.1, §12.3
- **Acceptance:**
  - Generates 3 variants per Photo with the documented widths/qualities/format.
  - Never upscales; auto-orients; strips metadata from output.
  - Unit test feeds a 200×100 source — all 3 outputs are 200×100 (no upscaling).
- **Commit:** `feat(backend): InterventionImageProcessor`

### T076 — Bind contracts in `AppServiceProvider`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T073, T074, T075
- **Parallel with:** —
- **Reads:** DESIGN.md §9.4
- **Acceptance:**
  - `register()` binds the three interfaces to the concrete services.
  - Test bindings (in `tests/CreatesApplication` or `Pest.php`) replace them with fakes.
- **Commit:** `chore(backend): bind service contracts`

### T077 — `App\Jobs\ProcessPhoto` job
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T076
- **Parallel with:** —
- **Reads:** DESIGN.md §12.3
- **Acceptance:**
  - `tries=3`, `backoff=[10,30,60]`.
  - `handle(ImageProcessor, ExifExtractor)` flow per §12.3.
  - `failed()` writes `processing_status=failed` and `processing_error`.
  - Pest test: dispatch + run synchronously, assert status transitions and exif populated.
- **Commit:** `feat(pipeline): ProcessPhoto job`

### T078 — Domain events: `PhotoUploaded`, `PhotoProcessed`, `PhotoDeleted`, `AlbumDeleted`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T013
- **Parallel with:** T077
- **Reads:** DESIGN.md §6.4 (rule 7), §12.2 step 5
- **Acceptance:**
  - Event classes carry the relevant model.
  - Listener stub registered for each (no-op for v1).
- **Commit:** `feat(backend): domain events`

### T079 — Wire `UploadPhotosAction` to dispatch real `ProcessPhoto` jobs
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T029, T077
- **Parallel with:** —
- **Reads:** DESIGN.md §12.2
- **Acceptance:**
  - `UploadPhotosAction` now wraps real `ProcessPhoto` instances in `Bus::batch`.
  - Pest integration test: upload a fixture image, run queue, assert variants exist on `Storage::fake()`.
- **Commit:** `feat(pipeline): wire UploadPhotosAction to ProcessPhoto`

### T080 — Phase 6 review + E2E processing test
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T077–T079
- **Acceptance:**
  - End-to-end: upload via API, run queue worker, poll `/photos/batch/{id}` → finished + completed photos.
  - GPS-bearing test fixture image: assert `exif` JSON contains no GPS keys after processing.
- **Commit:** `chore: Phase 6 review`

---

# Phase 7 — Filament queue monitoring (widgets, reprocess actions, storage page)

Depends on Phase 3 + Phase 6.

---

### T081 — `QueueMonitor` widget reads from real tables
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T051, T077
- **Parallel with:** T082, T083
- **Reads:** DESIGN.md §7.4
- **Acceptance:**
  - 3 stats cards with the documented colors.
  - Polls every 5 s.
- **Commit:** `feat(filament): real queue monitoring widget`

### T082 — `RecentUploadsTable` widget shows last 10
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T051
- **Parallel with:** T081, T083
- **Reads:** DESIGN.md §7.4
- **Acceptance:**
  - Last 10 photos by `created_at desc`; columns thumbnail, title, album, status, created_at.
  - Polls every 10 s.
- **Commit:** `feat(filament): recent uploads widget`

### T083 — Reprocess action + bulk action live
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T048, T077
- **Parallel with:** T081, T082
- **Reads:** DESIGN.md §7.1
- **Acceptance:**
  - Single reprocess dispatches a fresh `ProcessPhoto` and resets attempts/status.
  - Bulk reprocess works on selected rows.
- **Commit:** `feat(filament): reprocess actions wired to ProcessPhoto`

### T084 — `StorageManagement` actions live
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T052, T077
- **Parallel with:** —
- **Reads:** DESIGN.md §7.5
- **Acceptance:**
  - Regenerate-all chunks 100 photos at a time and dispatches `ProcessPhoto` for each.
  - Purge truncates `failed_jobs` after confirmation.
- **Commit:** `feat(filament): storage management actions`

### T085 — Phase 7 review
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T081–T084
- **Acceptance:**
  - Manually upload, kill the worker before processing — QueueMonitor shows pending; restart worker — count falls.
- **Commit:** `chore: Phase 7 review`

---

# Phase 8 — AWS migration (S3 + SQS via env vars)

---

### T086 — `.env.production.example` with two-bucket + SQS config
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T017
- **Parallel with:** T087, T088
- **Reads:** DESIGN.md §12.5
- **Acceptance:**
  - Every prod var from §12.5 present and commented.
- **Commit:** `chore(backend): production env example`

### T087 — `docs/DEPLOY.md` (S3 buckets, SQS, Supervisor)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T086
- **Parallel with:** T086, T088
- **Reads:** DESIGN.md §12.5, §12.6
- **Acceptance:**
  - Doc explains: create public + private buckets (Block Public Access ON for private); CORS for public; SQS standard queue; Supervisor config; cron entry; rollback notes.
- **Commit:** `docs: AWS deployment guide`

### T088 — `backend/scripts/smoke-aws.php`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T073, T086
- **Parallel with:** T086, T087
- **Reads:** DESIGN.md §12.5
- **Acceptance:**
  - Reads `.env.production`; writes a 1×1 JPEG to `photos_private`; reads it back via signed URL; dispatches a no-op job to SQS; cleans up. Prints PASS/FAIL per step.
- **Commit:** `chore(backend): AWS smoke test script`

### T089 — Audit hardcoded paths/disks
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T086
- **Parallel with:** —
- **Acceptance:**
  - `grep -rn "storage_path\|storage/app\|disk('public')\|file_get_contents" app/` finds no app-code matches outside `config/`.
- **Commit:** `chore: AWS migration audit (no changes)` (or fixes)

---

# Phase 9 — Hooks (auto-format, auto-test, safety)

Wave 0. Runs early but documented here for grouping.

---

### T090 — Backend dev tooling: Pint + Rector + QueryDetector
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T001
- **Parallel with:** T091
- **Reads:** CLAUDE.md "Code style"
- **Acceptance:**
  - `pint.json` (Laravel preset) + `rector.php` (php83 sets) committed.
  - `./vendor/bin/pint --test` passes on a fresh tree.
- **Commit:** `chore(backend): Pint + Rector configs`

### T091 — Frontend dev tooling: Prettier + ESLint
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T002
- **Parallel with:** T090
- **Reads:** CLAUDE.md "Code style"
- **Acceptance:**
  - `.prettierrc` and `eslint.config.js` committed; configured per CLAUDE.md.
  - `npx prettier --check .` passes.
- **Commit:** `chore(frontend): Prettier + ESLint configs`

### T092 — `lefthook.yml` at repo root
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T090, T091
- **Parallel with:** T093
- **Reads:** DESIGN.md §13 Phase 9
- **Acceptance:**
  - Pre-commit runs Pint (backend), Prettier (frontend), ESLint (frontend) on staged files.
  - `lefthook install` ran; a no-op commit succeeds.
- **Commit:** `chore: lefthook pre-commit hooks`

### T093 — `.claude/settings.json` PostToolUse hooks
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T090, T091
- **Parallel with:** T092
- **Magnet:** `.claude/settings.json`
- **Reads:** CLAUDE.md "Critical rules", DESIGN.md §13 Phase 9
- **Acceptance:**
  - PostToolUse on Edit/Write `backend/**/*.php` runs Pint.
  - PostToolUse on Edit/Write `frontend/**/*.{ts,tsx,css}` runs Prettier.
  - UserPromptSubmit safety hook blocks `rm -rf|git push --force|git reset --hard`.
- **Commit:** `chore: Claude Code hooks for auto-format and safety`

### T094 — `.github/workflows/ci.yml`
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T090, T091
- **Parallel with:** —
- **Magnet:** `.github/workflows/*`
- **Reads:** DESIGN.md §13 Phase 9
- **Acceptance:**
  - Two jobs: `backend` (PHP 8.3, composer install, migrate testing, php artisan test) and `frontend` (Node 20, npm ci, npm test, npm run build).
  - Triggers on push and PR.
- **Commit:** `ci: backend + frontend test pipelines`

---

# Phase 10 — Testing fill-gaps (Pest backend + Vitest frontend + Playwright E2E)

---

### T095 — Backend coverage gaps from §15
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T045, T080
- **Parallel with:** T096, T097
- **Reads:** DESIGN.md §15.2, §15.6
- **Acceptance:**
  - Coverage ≥ 80% lines (Pest `--coverage`).
  - Every test gate from §15.2 has at least one passing test.
- **Commit:** `test(backend): fill coverage gaps`

### T096 — Frontend coverage gaps
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T071
- **Parallel with:** T095, T097
- **Reads:** DESIGN.md §15.2
- **Acceptance:**
  - Vitest coverage ≥ 70%.
  - `useDebounce`, `useKeyboardShortcuts`, `useUpload`, `UploadModal`, `PhotoLightbox` all covered.
- **Commit:** `test(frontend): fill coverage gaps`

### T097 — Playwright E2E suite
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T071, T080
- **Parallel with:** T095, T096
- **Reads:** REQUIREMENTS §3 user stories, DESIGN.md §15.2
- **Acceptance:**
  - `playwright.config.ts` starts Laravel + Vite + queue:work via `webServer`.
  - 5 spec files exist (auth/upload/gallery/lightbox/batch).
  - All pass against a fresh stack.
- **Commit:** `test(e2e): full user workflows`

### T098 — axe-core a11y check in Playwright
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T097
- **Parallel with:** —
- **Reads:** REQUIREMENTS §NFR-4
- **Acceptance:**
  - Playwright runs `axe-core` against `/` and `/?photo=...`. Zero critical/serious findings.
- **Commit:** `test(a11y): axe-core checks in Playwright`

---

# Phase 11 — Git + GitHub MCP (git init, branches, PR, /commit skill)

Wave 0. Runs FIRST in practice.

---

### T099 — Initial repo + branch protection
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** —
- **Parallel with:** —
- **Reads:** DESIGN.md §13 Phase 11
- **Acceptance:**
  - GitHub repo created (private by default).
  - `main` branch protected: requires PR, requires status checks once CI exists.
- **Commit:** `chore: initial repository`

### T100 — PR template + CONTRIBUTING.md + CODEOWNERS
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T099
- **Parallel with:** —
- **Reads:** DESIGN.md §13 Phase 11, §15.4
- **Acceptance:**
  - `.github/PULL_REQUEST_TEMPLATE.md`, `docs/CONTRIBUTING.md`, `CODEOWNERS` committed.
- **Commit:** `docs: PR template and contributing guide`

### T101 — `/commit` slash command (Claude Code skill)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T100
- **Parallel with:** T102
- **Magnet:** `.claude/commands/`
- **Reads:** DESIGN.md §15.3 (commit conventions)
- **Acceptance:**
  - `.claude/commands/commit.md` exists; running `/commit` produces a Conventional-Commits-formatted message scoped per the change.
- **Commit:** `chore: /commit slash command`

### T102 — Demo PR through the workflow
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T100, T094
- **Parallel with:** T101
- **Acceptance:**
  - A no-op PR (e.g. README tweak) goes through CI, fills the template, gets squash-merged.
- **Commit:** `docs: demo PR through workflow`

---

# Phase 12 — Multi-agent development (3 agents, /spec-check, /principles-check)

---

### T103 — `scripts/worktree-new.sh` helper
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T099
- **Parallel with:** T104, T105
- **Reads:** DESIGN.md §14
- **Acceptance:**
  - Script creates `../pgp-<branch>` worktree for a branch name argument.
- **Commit:** `chore: worktree helper script`

### T104 — `/spec-check` slash command
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T101
- **Parallel with:** T103, T105
- **Magnet:** `.claude/commands/`
- **Acceptance:**
  - `.claude/commands/spec-check.md` reads SPEC sections cited in the current diff and reports drift.
- **Commit:** `chore: /spec-check slash command`

### T105 — `/principles-check` slash command
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T101
- **Parallel with:** T103, T104
- **Magnet:** `.claude/commands/`
- **Acceptance:**
  - `.claude/commands/principles-check.md` evaluates the diff against CLAUDE.md "Engineering principles" and lists violations with file:line citations.
- **Commit:** `chore: /principles-check slash command`

### T106 — `docs/PARALLEL-WORKFLOW.md` + demo
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T103
- **Parallel with:** —
- **Reads:** DESIGN.md §14
- **Acceptance:**
  - Doc explains worktree pattern + agent dispatch.
  - Two parallel demo branches (`feat/api-stats` + `feat/page-stats`) both land cleanly.
- **Commit:** `docs: parallel workflow with demo`

---

# Phase 13 — Final assembly & deployment

---

### T107 — `docs/SPEC-COVERAGE.md` audit
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T095, T096, T097
- **Parallel with:** —
- **Reads:** DESIGN.md (every section)
- **Acceptance:**
  - Every subsection of DESIGN.md mapped to a code location, test location, status (✅/⚠️/❌). Zero ❌ rows allowed.
- **Commit:** `docs: spec coverage audit`

### T108 — Lighthouse + bundle size budget
- **Status:** [ ]
- **Owner:** frontend-dev
- **Depends on:** T071
- **Parallel with:** T109
- **Reads:** REQUIREMENTS §NFR-1
- **Acceptance:**
  - Lighthouse desktop ≥ 90 perf, ≥ 95 a11y on the gallery page with 30 photos.
  - Main bundle ≤ 200 KB gzip.
- **Commit:** `perf(frontend): Lighthouse and bundle size budgets met`

### T109 — Security audit
- **Status:** [ ]
- **Owner:** code-reviewer
- **Depends on:** T107
- **Parallel with:** T108
- **Reads:** REQUIREMENTS §NFR-3, DESIGN.md §10
- **Acceptance:**
  - `npm audit --production` and `composer audit` both pass with no high/critical.
  - CORS allows only FRONTEND_URL, not `*`.
  - Sanctum 24h TTL confirmed in env.
  - No `.env*` committed.
- **Commit:** `chore: security audit (no changes)` (or fixes)

### T110 — README.md (root)
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T107
- **Parallel with:** T111
- **Acceptance:**
  - Root `README.md` describes the project, points to REQUIREMENTS / DESIGN / TASKS / CLAUDE / DEPLOY.
  - Quickstart section: clone, install, migrate, seed, run.
- **Commit:** `docs: root README`

### T111 — Staging deploy
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T086, T088, T109
- **Parallel with:** T110
- **Reads:** DESIGN.md §12.5, docs/DEPLOY.md
- **Acceptance:**
  - Staging stack online; smoke-aws.php passes; Playwright E2E passes against staging URL.
- **Commit:** `chore: staging deploy verified`

### T112 — Production cutover checklist
- **Status:** [ ]
- **Owner:** backend-dev
- **Depends on:** T111
- **Parallel with:** —
- **Reads:** docs/DEPLOY.md
- **Acceptance:**
  - Checklist in DEPLOY.md ticked: DB backups, S3 lifecycle, CloudWatch alarms, Sentry DSN, rollback plan.
  - GitHub release `v1.0.0` created with auto-generated notes.
- **Commit:** `chore: production cutover checklist`

---

## Task summary

| Phase | Range | Count | Owner mix | Wave |
|---|---|---|---|---|
| 1 — Backend foundation | T001–T018 | 18 | backend-dev + reviewer | 0 |
| 2 — API layer | T019–T045 | 27 | backend-dev + reviewer | 1 (Track A) |
| 3 — Filament admin | T046–T054 | 9 | backend-dev + reviewer | 1 (Track B) |
| 4 — Frontend setup | T055–T062 | 8 | frontend-dev + reviewer | 1 (Track C) |
| 5 — React components | T063–T071 | 9 | frontend-dev + reviewer | 1 (Track C cont.) |
| 6 — Image pipeline | T072–T080 | 9 | backend-dev + reviewer | 1 (Track A cont.) |
| 7 — Queue monitoring | T081–T085 | 5 | backend-dev + reviewer | 2 |
| 8 — AWS migration | T086–T089 | 4 | backend-dev + reviewer | 2 |
| 9 — Hooks | T090–T094 | 5 | mixed | 0 |
| 10 — Testing | T095–T098 | 4 | mixed | 2 |
| 11 — Git + GitHub | T099–T102 | 4 | backend-dev | 0 |
| 12 — Multi-agent | T103–T106 | 4 | backend-dev | 3 |
| 13 — Final assembly | T107–T112 | 6 | mixed | 3 |
| **Total** | | **112** | | |

---

## Quick parallelization reference

**Tasks safe to run as parallel subagents** (within the same phase, disjoint files):

- T004 ‖ T005 ‖ T006 (Phase 1 — enums and config independent)
- T008 ‖ T009 (Phase 1 — albums vs tags migrations)
- T023 ‖ T024 ‖ T025 ‖ T026 (Phase 2 — form requests + resources)
- T030 ‖ T031 ‖ T034 ‖ T035 ‖ T036 (Phase 2 — distinct controllers)
- T040 ‖ T041 ‖ T042 ‖ T043 ‖ T044 (Phase 2 — independent test files)
- T047 ‖ T049 ‖ T051 (Phase 3 — distinct Filament resources)
- T055 ‖ T056 ‖ T057 (Phase 4 — independent setup tasks)
- T064 ‖ T065 ‖ T066 ‖ T067 ‖ T068 ‖ T069 (Phase 5 — disjoint component trees)
- T073 ‖ T074 ‖ T075 (Phase 6 — distinct concrete services)
- T081 ‖ T082 ‖ T083 (Phase 7 — distinct widgets/actions)
- T086 ‖ T087 ‖ T088 (Phase 8 — independent docs/scripts)
- T090 ‖ T091 (Phase 9 — backend vs frontend tooling)
- T095 ‖ T096 ‖ T097 (Phase 10 — independent test layers)

**Magnet files (run serially, one agent at a time):**
- `bootstrap/app.php` (T020, T039)
- `routes/api.php` (T039)
- `vite.config.ts`, `src/index.css`, `App.tsx`, `main.tsx` (T055, T060)
- `app/Providers/Filament/AdminPanelProvider.php` (T046, T051)
- `.claude/settings.json` (T093, T101, T104, T105)
- `.github/workflows/ci.yml` (T094)

---

**End of TASKS.md** — when every checkbox is `[x]`, run T112 to ship.
