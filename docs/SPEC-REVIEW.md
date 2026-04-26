# SPEC.md Review — PhotoGallery Pro

> Comprehensive review of the advanced-course plan against SOLID, REST, KISS, plus discipline-specific best practices for backend (Laravel), frontend (React/TypeScript), and database (MySQL/Postgres). Includes a parallelization plan and a test-and-commit discipline. Date: 2026-04-26.
>
> **Status:** clusters 1–7 below have been folded into `SPEC.md` itself. This file is preserved as the rationale + decision log.

---

# 1. Architectural review

## 1.1 SOLID

| # | Issue | Section | Principle | Fix |
|---|---|---|---|---|
| S1 | `ImageProcessor` / `ExifExtractor` are concrete; consumers bind directly | §3.2, §11.2 | Dependency Inversion | Define `App\Contracts\{ImageProcessor,ExifExtractor}` interfaces; bind in `AppServiceProvider` |
| S2 | `PhotoController@index` is the only home for search + multi-tag AND + album + favorites + sort | §6.2 | Single Responsibility | Extract to `App\Queries\PhotoQuery` value object OR `Photo` query scopes (`scopeWithSearch`, `scopeWithTags`) so Filament can reuse |
| S3 | Tag upsert logic duplicated across 3 controllers (POST /photos, PATCH /photos, POST /photos/batch) | §6.2 | DRY / SRP | `App\Services\TagAssigner::syncByNames(Photo, array): void` |
| S4 | Filament `PhotoResource` and API `PhotoResource` collide on class name | §3.2 | Readability (KISS+SRP) | Rename API resources: `PhotoData` / `AlbumData` / `TagData` |
| S5 | `App\Services\StorageManager` and `App\Jobs\ProcessBatchUpload` are referenced in §3.2 but not used anywhere — orphans | §3.2, §7.5, §11.3 | YAGNI | Delete from §3.2 |
| S6 | `PhotoObserver::deleted()` deletes files; `ProcessPhoto` writes them. File lifecycle is split between two layers with no contract | §11.2, §3.2 | SRP / Open-Closed | Either (a) make `PhotoObserver` the only writer too, or (b) accept the split but document it as "writes via job, deletes via observer" |

## 1.2 REST

| # | Issue | Section | Fix |
|---|---|---|---|
| R1 | `POST /photos/{id}/favorite` is a non-idempotent toggle | §6.2 | Replace with `PUT` (mark) + `DELETE` (unmark) on `/photos/{id}/favorite`, OR fold into `PATCH /photos/{id}` and delete the endpoint |
| R2 | Inconsistent envelopes — auth and favorite-toggle return unwrapped, everything else wraps in `data` | §6.0 | Wrap everything: `{ "data": { "token": "...", "user": {...} } }` |
| R3 | Tag input is *names*, tag filter is *slugs* — same param name, different semantics | §6.2 | Slugs everywhere; add separate `new_tags[]: string[]` for novel names |
| R4 | Batch endpoint requires ≥ 2 files — frontend must branch | §6.2, §8.7 | `min:1|max:20` on `/photos/batch`, retire `POST /photos`, frontend always uses one path |
| R5 | Two parallel polling loops after batch upload (per-photo + batch progress) | §8.7 | Embed photo statuses inside `GET /photos/batch/{id}` response so one poll suffices |
| R6 | `POST /photos` returns `201` while `POST /photos/batch` returns `202` — both are async | §6.6 | Both `202 Accepted`; add `Location: /photos/{id}` header |
| R7 | `GET /tags` is labeled "unwrapped" but uses `{data: [...]}` | §6.4 | Pick a label and stick to it |
| R8 | Rate limits are per-IP only — shared NAT/proxies break this | §6.0 | Add per-user limit on top of per-IP |
| R9 | No `ETag` / `Last-Modified` on photo resources — clients can't avoid re-fetch | §6.2 | Emit `ETag: W/"<photo.updated_at>"`; honor `If-None-Match` → `304` |
| R10 | `DELETE` cascade behavior not surfaced in the API contract | §6.3 | Document explicitly in response body or via `?cascade=` query param |

## 1.3 KISS

| # | Issue | Section | Fix |
|---|---|---|---|
| K1 | Sort param mixes naming styles (`newest`, `oldest`, `title_asc`, `favorites_first`) | §6.2 | Split into `sort=created_at\|title\|favorites` + `order=asc\|desc` |
| K2 | `is_favorite` accepts `0`, `1`, `true`, `false` — 4 representations | §6.2 | One per content type |
| K3 | Two files named `shortcuts.ts` (lib/ and data/) | §3.3 | Delete `lib/shortcuts.ts` |
| K4 | `PHOTOS_DISK=public` is misleading | §11.4 | Rename env to `PHOTOS_DRIVER=local\|s3` |
| K5 | Migration filenames hardcoded to `2026_01_01_*` | §3.2 | Replace with `<generated>_*` placeholders |
| K6 | Polling intervals scattered as inline constants | §7.4, §8.7 | Centralize in `data/polling.ts` and `config/photogallery.php` |
| K7 | Tailwind v4 rules duplicated in CLAUDE.md and SPEC.md | both | CLAUDE.md becomes a one-liner pointer to SPEC.md §8.12 |

---

# 2. Discipline best practices

## 2.1 Backend (Laravel)

**Already correct in spec:** Form Requests, API Resources, enums for status, Storage facade everywhere, DB-enforced FKs with explicit cascade actions, Sanctum.

**Missing or weak:**

| # | Item | Where | Fix |
|---|---|---|---|
| B1 | No `DB::transaction()` around multi-write operations | §6.2 | Wrap `POST /photos` (Photo + tags + dispatch) and `PATCH /photos` |
| B2 | No `withCount('photos')` documented on Album/Tag listings — N+1 risk | §6.3, §6.4 | Add explicit `->withCount('photos')` |
| B3 | No eager loading discipline documented | §6.5 | Force `with(['album:id,name', 'tags:id,name,slug'])` in every list query |
| B4 | No domain events | — | `PhotoUploaded`, `PhotoProcessed`, `PhotoDeleted` events |
| B5 | No queue worker timeout coordination — `--timeout=120` will kill `ProcessPhoto` mid-resize on a large image | §11.5 | Document max image dimension/file size budget |
| B6 | No alerting on failed jobs | §7.4 | Add Sentry/Bugsnag integration |
| B7 | No path param validation | §6.2 | Add UUID route constraints OR use route model binding |
| B8 | Mass-assignment surface | implicit | Hard rule: `$request->validated()`, never `all()` |
| B9 | Sanctum tokens have no scopes/abilities | §9.2 | Add abilities (`upload`, `manage-albums`) |
| B10 | No log channel discipline | §10 | Dedicated `processing` channel |
| B11 | No health endpoint | §13 phase 8 | Move to Phase 1 |

## 2.2 Frontend (React/TypeScript)

**Already correct:** TanStack Query, React Router 7 data API, centralized API client, strings extracted to `data/`, Tailwind v4 only.

**Missing or weak:**

| # | Item | Where | Fix |
|---|---|---|---|
| F1 | No error boundaries — a single crash kills the whole tree | §8 | Wrap Lightbox, UploadModal, each Page in ErrorBoundary |
| F2 | No code splitting — every page loads on first paint | §8.1 | `React.lazy` + Suspense |
| F3 | No bundle budget | §13 phase 13 | Main < 200 KB gzip, route chunks < 100 KB |
| F4 | No `srcset`/`sizes` on `<img>` | §8.5 | Serve responsive variants |
| F5 | No `decoding="async"` and no `fetchpriority` | §8.5 | Add both |
| F6 | No focus trap in `Modal`/`Lightbox` | §8.6 | `react-focus-lock` |
| F7 | No reduced-motion handling | §8 | `@media (prefers-reduced-motion)` |
| F8 | TanStack Query invalidation not documented per mutation | §8.10 | Each mutation hook lists which query keys to invalidate |
| F9 | Polling not cleaned up on unmount | §8.7 | `useEffect` cleanup + `AbortController` |
| F10 | `useKeyboardShortcuts` registers on `window` — multiple instances stack | §8.8 | Singleton context provider |
| F11 | No CSP headers | §13 phase 13 | Strict CSP |
| F12 | No type strictness rule | — | `tsconfig.json`: `"strict": true`, `"noUncheckedIndexedAccess": true` |
| F13 | Synchronous `localStorage` reads on every request | §8.10 | Module-scope cache, sync on auth events |
| F14 | No prefetching of next/prev photo in lightbox | §8.6 | TanStack Query prefetch |
| F15 | No optimistic updates on favorite toggle | §8.5 | `useMutation` with `onMutate` |

## 2.3 Database

**Already correct:** UUIDs everywhere, all FKs at DB level with explicit cascade, single index per FK, composite PK on pivot, enum for `processing_status`.

**Missing or weak:**

| # | Item | Where | Fix |
|---|---|---|---|
| D1 | No composite index for `WHERE album_id = ? ORDER BY created_at DESC` | §4.4 | Add `INDEX (album_id, created_at)` |
| D2 | No composite index for "favorites_first" sort | §4.4 | Add `INDEX (is_favorite, created_at)` |
| D3 | No composite index for queue dashboard | §4.4 | Add `INDEX (processing_status, created_at)` |
| D4 | Pagination is offset-based — degrades after ~10k photos | §6.2 | Switch to cursor pagination |
| D5 | No soft deletes | §4 | Decide explicitly; add `deleted_at` if undo is in scope |
| D6 | UUID v7 mentioned in §6.0 but `HasUuids` defaults to v4 | §4 conventions | Override `newUniqueId()` with `Str::uuid7()` |
| D7 | `exif` JSON column is unindexed and never queried — fine, but document | §4.4 | Add note: never `WHERE exif->...` |
| D8 | `description` indexed via `LIKE '%q%'` = full table scan | §4.4, §6.2 | FULLTEXT(title, description) + `MATCH ... AGAINST` |
| D9 | No test seeders separate from prod seeds | §3.2 | `database/seeders/Test/` |
| D10 | No documented backup strategy | §13 phase 13 | RDS automated backups + S3 lifecycle + restore drill |
| D11 | No connection pool guidance for SQS workers | §11.5 | `DB_PERSISTENT_CONNECTIONS=false` |
| D12 | `failed_jobs.exception LONGTEXT` — full stack traces are huge | §4.7 | Document weekly `queue:flush` |

---

# 3. Parallelization plan

## 3.1 Dependency graph (existing §13 phases)

```
Phase 1 (DB) ─┬─> Phase 2 (API) ─┬─> Phase 5 (React components, real data)
              │                   │
              ├─> Phase 3 (Filament) ──> Phase 7 (Queue monitoring)
              │                   ▲
              └─> Phase 6 (Image pipeline) ─┘
                                  │
                                  └─> Phase 8 (AWS migration)

Phase 4 (Frontend scaffold) ──> Phase 5 (React components, mocked) ──┐
                                                                      │
Phase 9 (Hooks) ─── runs anytime ─────────────────────────────────────┤
Phase 11 (Git/PR setup) ── ideally before Phase 1 ────────────────────┤
                                                                      ▼
                                                         Phase 10 (Test fill-gaps)
                                                                      │
                                                                      ▼
                                                         Phase 13 (Deploy)
Phase 12 (Multi-agent docs) ── independent ──────────────────────────┘
```

## 3.2 Wave-by-wave execution

**Wave 0 — Sequential foundation:**
- Phase 11 (Git + PR setup)
- Phase 9 (Hooks)
- Phase 1 (Backend foundation)

**Wave 1 — Three parallel tracks:**

| Track | Phases | Owner |
|---|---|---|
| A — Backend API + Pipeline | Phase 2 → Phase 6 | Agent A |
| B — Filament admin | Phase 3 | Agent B |
| C — Frontend | Phase 4 → Phase 5 (mocked API) | Agent C |

**Wave 2 — Convergence:**
- Phase 5 swaps mocks for real API
- Phase 7 (depends on 3 + 6)
- Phase 8 (depends on 6)
- Phase 10 (test fill-gaps)

**Wave 3 — Sequential close-out:**
- Phase 13

**Wall-clock estimate:** sequential ~15–20 days; parallel with 3 tracks ~8–10 days.

## 3.3 Intra-phase parallelization

**Phase 2 — 4 parallel sub-agents:**
- 2a: AuthController + tests
- 2b: PhotoController CRUD + index/filtering + tests
- 2c: BatchUploadController + tests
- 2d: AlbumController + TagController + tests
Sync point: shared exception handler in `bootstrap/app.php` — assign to one agent first.

**Phase 3 — 3 parallel sub-agents:**
- 3a: PhotoResource
- 3b: AlbumResource + PhotosRelationManager + TagResource
- 3c: Widgets + StorageManagement page
Sync point: `AdminPanelProvider` — serial registration after parallel work lands.

**Phase 5 — 4 parallel sub-agents:**
- 5a: Layout (Shell, Navbar, Sidebar)
- 5b: Gallery (MasonryGrid, PhotoCard, ProcessingOverlay)
- 5c: Lightbox (PhotoLightbox, ExifPanel)
- 5d: Upload + LoginPage + useAuth
Sync points: shared `src/api/*.ts`, `src/data/copy.ts`, `src/lib/queryClient.ts` — written in Phase 4.

**When NOT to parallelize:**
- `bootstrap/app.php`
- Migrations (filenames must be ordered)
- `AdminPanelProvider` registrations
- `src/index.css` `@theme` block
- `.env.example`
- `composer.json` / `package.json`

---

# 4. Test-and-commit discipline

## 4.1 What is "a task"?

A task is a single verifiable deliverable that can be committed in isolation:
- One migration
- One controller method (or one Form Request + Resource pair)
- One Filament resource page
- One React component (or one custom hook)
- One job class
- One service class

Anti-patterns: "Implement the API" (too big, that's a phase), "Wire it up" (no verification possible), "Refactor stuff" (too vague).

## 4.2 Test gates per task type

| Task type | Required test before commit |
|---|---|
| Migration | `php artisan migrate:fresh` succeeds; rollback succeeds |
| Eloquent model | Factory creates a row; relationship resolves; cast works |
| Form Request | One test per validation rule (happy + each failure) |
| API endpoint | One feature test per documented status code in §6.6 |
| Service | Unit test with all dependencies stubbed |
| Job | Feature test that runs the job synchronously and asserts side effects |
| Filament resource | Smoke test (Filament has Livewire test helpers) |
| React hook | Vitest with `renderHook` |
| React component | RTL test for golden path + one error path |
| E2E flow | Playwright test for the full user journey |

**Rule:** A task is not done until its tests are green. No "tests later".

## 4.3 Commit conventions

- One task = one commit
- Conventional Commits: `<type>(<scope>): <subject>`
- Types: `feat`, `fix`, `chore`, `test`, `docs`, `refactor`, `perf`
- Scopes: `backend`, `frontend`, `db`, `filament`, `api`, `pipeline`, `ci`, `spec`
- Subject ≤ 70 chars, imperative voice
- Body (optional): why, not what; reference SPEC.md sections
- Footer: `Refs SPEC.md §6.2` or `Closes #123`

## 4.4 Branch + PR workflow

- `main` is protected
- Feature branches: `feat/<phase>-<task>` (e.g. `feat/2-api-photos-store`)
- One PR per task
- PR template requires: summary, test plan with checked boxes, screenshot for FE, SPEC.md section reference
- CI must pass: backend tests + frontend tests + lint + build
- Squash merge

## 4.5 Test failure → revert, not skip

If a test introduced in Task N starts failing during Task N+M:
1. `git bisect` to find the breaking commit
2. Either fix forward in a `fix:` commit, OR revert the breaking commit
3. Never `--no-verify`, never delete the test, never `.skip`

---

# 5. Recommended action order (status: applied)

1. ✅ Critical security/architecture (cluster 1)
2. ✅ REST contract uniformity (cluster 2)
3. ✅ Backend hardening (cluster 3)
4. ✅ Frontend hardening (cluster 4)
5. ✅ Database performance (cluster 5)
6. ✅ Process additions to §13 (cluster 6)
7. ✅ KISS cleanup (cluster 7)

Each cluster landed as a discrete edit to SPEC.md so the spec remains the single source of truth. This document is the rationale + decision log.
