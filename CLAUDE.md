# CLAUDE.md — PhotoGallery Pro

> The project constitution. Loaded into every Claude Code session. **Authoritative for HOW we work**; defers to `docs/REQUIREMENTS.md` (what to build) and `docs/DESIGN.md` (technical blueprint) for content. Atomic work lives in `docs/TASKS.md`.

---

## Documentation map

| File | Purpose | When to read |
|---|---|---|
| `CLAUDE.md` (this file) | How we work — rules, principles, style | Every session, automatic |
| `docs/REQUIREMENTS.md` | What we're building, in plain language | Before starting any task — read the relevant feature |
| `docs/DESIGN.md` | Technical blueprint — schemas, contracts, file paths | Before implementing — read the section the task references |
| `docs/TASKS.md` | 80+ atomic tasks across 13 phases | Pick a task; check it off when done |
| `docs/SPEC-REVIEW.md` | Decision log explaining why architectural choices were made | When you disagree with a rule and want context |

---

## Project overview

PhotoGallery Pro is a full-stack photo gallery: users upload photos (JPG/PNG/WebP, ≤10 MB each, up to 20 at a time), organize them into albums, tag them, and browse via a masonry-grid React SPA with lightbox + keyboard navigation. The backend is a Laravel 13 REST API under `/api/v1` with a Filament v5 admin panel mounted at `/admin`. Image resizing and EXIF extraction run as queued background jobs so uploads return immediately. Same code runs locally (DB queue + local disk) and in production (SQS + S3) — only env vars change.

---

## Tech stack (with install commands)

### Backend (`backend/` — Laravel 13)

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3+ | Strict types where practical |
| Laravel | 13.x | Skeleton via `composer create-project` |
| Filament | 5.x | Mounted at `/admin`, `web` guard |
| Sanctum | 4.x | **Token mode** (24h TTL) — see DESIGN.md §9 |
| Intervention Image | 3.x | Via `intervention/image-laravel` |
| Pest | 3.x | Feature + unit tests |

```bash
# Scaffold
composer create-project laravel/laravel backend "^13.0"
cd backend

# Runtime deps
composer require filament/filament "^5.0" \
                 intervention/image-laravel "^3.0" \
                 laravel/sanctum "^4.0" \
                 league/flysystem-aws-s3-v3 "^3.0"

# Dev deps
composer require --dev laravel/pint rector/rector pestphp/pest \
                       beyondcode/laravel-query-detector

# Required Laravel migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan queue:table
php artisan queue:failed-table
php artisan queue:batches-table
# CRITICAL: edit Sanctum migration so tokenable_id is CHAR(36) — see DESIGN.md §4.6
```

### Frontend (`frontend/` — React 19 + Vite 6)

| Component | Version | Notes |
|---|---|---|
| Node | 20 LTS+ | |
| React | 19.x | Function components only |
| Vite | 6.x | With `@vitejs/plugin-react` |
| Tailwind CSS | **4.x** | Via `@tailwindcss/vite` only |
| React Router | 7.x | Data router API |
| TanStack Query | 5.x | Server state, caching, polling |
| Axios | 1.x | With auth + error interceptors |
| Vitest + RTL | latest | |
| Playwright | latest | E2E |

```bash
# Scaffold
npm create vite@latest frontend -- --template react-ts
cd frontend

# Runtime deps
npm install react-router-dom@^7 \
            @tanstack/react-query@^5 \
            axios@^1 \
            lucide-react \
            sonner \
            react-focus-lock

# Dev deps
npm install -D @tailwindcss/vite tailwindcss@^4 \
               vitest @testing-library/react @testing-library/user-event \
               @testing-library/jest-dom jsdom \
               @playwright/test \
               prettier eslint @typescript-eslint/eslint-plugin \
               @typescript-eslint/parser eslint-plugin-react-hooks \
               vite-bundle-visualizer
```

---

## Critical rules

These are **non-negotiable**. Violations require an explicit user override.

### Workflow rules

1. **Always read `docs/REQUIREMENTS.md` and `docs/DESIGN.md` before implementing tasks.** Each task in `TASKS.md` lists which sections to read. If the task is ambiguous and the docs don't resolve it, stop and ask — do not guess.
2. **Always update `docs/TASKS.md` checkboxes after completing work.** Mark `[x]` the moment a task's acceptance criteria are met. Don't batch — checking off six tasks at once means you've drifted.
3. **Use subagents for tasks marked `parallel: true` in `TASKS.md`.** Tasks with disjoint file scopes are designed to run concurrently in worktrees. Sequential execution wastes wall-clock time on parallelizable work.
4. **One task = one commit.** Conventional Commits format: `<type>(<scope>): <subject>`. Reference SPEC sections in the body. See DESIGN.md §15.3.
5. **Tests run before commits.** Test gates per task type live in DESIGN.md §15.2. No `--no-verify`, no `.skip`, no "tests later".

### Architecture rules (cite DESIGN.md sections in PRs)

6. **`docs/DESIGN.md` is authoritative for code.** Column types, response shapes, validation rules, file paths — all defined there. Do NOT invent fields, endpoints, or directories.
7. **Every endpoint wraps its payload in `{ "data": ... }`.** Auth, favorite, batch — all wrapped. No exceptions. (DESIGN.md §6.0)
8. **Every primary key is UUID v7** via the `HasUuidV7` trait. Stored as `CHAR(36)`. Never auto-increment for application tables.
9. **Two photo disks. All file I/O goes through them.**
   - `Storage::disk('photos')` — public-read; sized variants only
   - `Storage::disk('photos_private')` — private; originals only; signed URLs only

   Never use `File::`, `fopen`, `copy`, or `move_uploaded_file`. (DESIGN.md §11)
10. **Authorization, not just authentication.** Every mutating controller calls `$this->authorize($action, $model)` AND `$request->user()->tokenCan('photos:write'|'albums:write')`. (DESIGN.md §6.4a, §9)
11. **Wrap multi-write operations in `DB::transaction(...)`.** Photo upload (insert + tag attach + dispatch), photo update (update + tag sync), album with cover. (DESIGN.md §6.4a)
12. **Eager-load explicitly.** Every list/show endpoint declares `with([...])` and `withCount([...])`. N+1 is a bug — `Beyondcode\QueryDetector` catches it in tests.
13. **Tailwind v4 setup is non-negotiable.** No `tailwind.config.js`, no `postcss.config.js`, no `autoprefixer`. Theme tokens go inside `@theme { ... }` in `src/index.css`. Authoritative content in DESIGN.md §8.12 — don't duplicate.
14. **All UI strings live in `frontend/src/data/`.** No hardcoded English strings inside JSX. Components import from `data/copy.ts`.

---

## Engineering principles (project-specific)

Generic principle definitions are useless. Here's how each applies to **this** codebase.

### SOLID

**S — Single Responsibility.** A class owns one reason to change.
- `App\Services\Imaging\InterventionImageProcessor` resizes images. It does NOT extract EXIF, does NOT save Photo records, does NOT dispatch jobs. If you find yourself adding "and also do X" to a service, X belongs elsewhere.
- `App\Http\Controllers\Api\V1\PhotoController` orchestrates request → response. The actual work (resize, tag sync, EXIF strip) lives in services and jobs. Controllers are thin coordinators.
- ❌ Anti-example: `Photo::uploadAndProcess()` static method on the model. ✅ Right: `PhotoUploadAction::__invoke($files, $user)` in `App\Actions\Photo\`.

**O — Open/Closed.** Code is open for extension, closed for modification.
- New sort orders for `GET /photos` go into `App\Queries\PhotoQuery::applySort()` as new cases — adding one shouldn't require editing the controller.
- New image variants (e.g. "social" 1200px) go into `ImageProcessor::generate()` via a config-driven loop, not hardcoded if/else.

**L — Liskov Substitution.** A subtype must be usable wherever its supertype is.
- `App\Contracts\ImageProcessor` interface — both `InterventionImageProcessor` (production) and `FakeImageProcessor` (tests) must satisfy the same contract: same method signatures, same exceptions, same side effects on the Photo row. Tests must not need to know which is bound.

**I — Interface Segregation.** Clients shouldn't depend on methods they don't use.
- Don't put `extract()` and `stripGps()` in the same interface as `generate()` — they're separate concerns. `App\Contracts\ImageProcessor` and `App\Contracts\ExifExtractor` are intentionally split.

**D — Dependency Inversion.** Depend on abstractions, not concretes.
- `ProcessPhoto` job constructor signature: `public function __construct(public Photo $photo) {}`, `handle(ImageProcessor $processor, ExifExtractor $exif)` — never `new InterventionImageProcessor()` inside the job.
- `AppServiceProvider::register()` binds the contracts to concrete implementations. Tests bind fakes in `setUp()`.

### REST

The API at `/api/v1` is the contract. Apply REST grammar:

- **Resources, not actions.** ✅ `PUT /photos/{id}/favorite` and `DELETE /photos/{id}/favorite` (favorite is a sub-resource). ❌ `POST /photos/{id}/toggle-favorite` (toggle is a verb).
- **Idempotency.** PUT, DELETE, GET must be safe to retry. POST need not be — but pair it with a server-generated UUID so duplicate posts can be detected.
- **Status codes match meaning.** `201 Created` only when the resource is fully ready. Photo upload returns `202 Accepted` because processing is async.
- **Uniform envelope.** Every endpoint wraps in `{ "data": ... }`. List endpoints add `links` and `meta` (cursor pagination). No exceptions for "convenience".
- **Cache hints.** `GET /photos/{id}` emits `ETag: W/"<sha1(updated_at)>"`. Clients send `If-None-Match` → `304 Not Modified` saves bandwidth.
- **Errors use a uniform envelope** (DESIGN.md §10.1) — `{ "message": "...", "errors": { "field": [...] } }`. Frontend never needs to special-case error shapes.

### KISS

The smallest implementation that meets the acceptance criteria wins.

- One upload endpoint (`POST /photos` accepts 1–20 files), not two. Frontend doesn't branch.
- One polling loop after upload (`GET /photos/batch/{id}` includes per-photo statuses), not two.
- Cursor pagination, not page+offset+`total` (the gallery doesn't need a count).
- Tags are a flat global vocabulary, not a hierarchy. v1 has no tag categories, no tag descriptions, no tag colors.
- Soft deletes are out of scope for v1. Deletes are permanent. We can add `deleted_at` later if undo becomes a requirement.
- ❌ Anti-example: introducing a `PhotoFactory` interface so the factory itself is swappable. ✅ Right: just use Laravel's `Photo::factory()` directly.

### DRY

Don't repeat yourself, but also don't pre-abstract.

- **Tag upsert lives in `App\Services\TagAssigner::syncByNames(Photo, array)`** — used by `POST /photos`, `PATCH /photos`, and the Filament `PhotoResource` form. One implementation, three call sites.
- **Filter logic for `GET /photos` lives in `App\Queries\PhotoQuery`** — reused by the Filament admin index page. Don't reimplement search/sort in two places.
- **Eager-loading lists are constants** on the Resource: `PhotoResource::with = ['album:id,name', 'tags:id,name,slug', 'user:id,name']`. Controllers reference the constant.
- **UI strings live once** in `frontend/src/data/copy.ts`. Components import; never inline.
- **DON'T** abstract until the second occurrence. The third occurrence is when extraction becomes required.

---

## Parallelization rules

(Full plan in DESIGN.md §14. Operational summary here.)

**Wave 0 — sequential foundation (no parallelism):**
- Phase 11 (Git/PR setup) → Phase 9 (Hooks) → Phase 1 (Backend foundation)

**Wave 1 — three parallel tracks (each in its own git worktree):**
- Track A: Phase 2 (API) → Phase 6 (Image pipeline)
- Track B: Phase 3 (Filament admin)
- Track C: Phase 4 (Frontend scaffold) → Phase 5 (Components, mocked API)

Tracks share zero files. Sync at end of Wave 1: Track C swaps mocks for real API.

**Wave 2 — convergence with limited parallelism:**
- Phase 7 (queue monitoring) ‖ Phase 8 (AWS) ‖ Phase 10 (test fill-gaps)

**Wave 3 — sequential close-out:** Phase 12 → Phase 13.

**Files that NEVER edit in parallel** (merge conflict magnets — assign to one agent serially):
- `bootstrap/app.php`
- `database/migrations/*` (filenames must be ordered)
- `app/Providers/Filament/AdminPanelProvider.php`
- `frontend/src/index.css` (`@theme` block)
- `frontend/src/App.tsx` (route table)
- `frontend/src/main.tsx` (provider tree)
- `.env.example` / `.env.production.example`
- `composer.json` / `package.json`
- `lefthook.yml` / `.claude/settings.json` / `.github/workflows/*`

**When picking a task in `TASKS.md`:**
- If `parallel_with` is non-empty, you CAN dispatch a subagent (use the Agent tool) for one of those tasks while you work on yours.
- If a task touches a magnet file, claim it serially — broadcast in the team channel before starting.

---

## Code style

### PHP
- **Pint** with Laravel preset (`backend/pint.json`). Auto-runs via Claude Code `PostToolUse` hook on `*.php` files; pre-commit via lefthook.
- **PSR-12** baseline; Pint enforces deviations.
- **Strict types** (`declare(strict_types=1);`) on every file in `app/Services/`, `app/Actions/`, `app/Jobs/`, `app/Queries/`.
- **Type hints on everything**: parameters, return types, properties (PHP 8 promoted properties preferred for DTOs).
- **Final by default.** New classes are `final class Foo` unless explicitly designed for inheritance. Prevents accidental coupling.
- **No facades inside models.** Inject services through the IoC container.
- **Mass assignment:** `$model->fill($request->validated())` — never `$request->all()`.

### TypeScript / React
- **Prettier** (`frontend/.prettierrc`): `singleQuote: true`, `semi: true`, `printWidth: 100`. Auto-runs on `*.{ts,tsx,css}`.
- **ESLint** with `@typescript-eslint` + `eslint-plugin-react-hooks`. Rules of hooks enforced.
- **`tsconfig.json`:** `"strict": true`, `"noUncheckedIndexedAccess": true`, `"noImplicitOverride": true`, `"exactOptionalPropertyTypes": true`. `any` is banned (`@typescript-eslint/no-explicit-any: error`); use `unknown` + narrowing.
- **Function components only.** No class components.
- **Named exports** for components and hooks.

### Tailwind v4
- `@tailwindcss/vite` plugin only. Theme tokens in `@theme { ... }` inside `src/index.css`.
- **No** `tailwind.config.js`, `postcss.config.js`, or `autoprefixer`.
- Authoritative setup: DESIGN.md §8.12.

### Git
- Conventional Commits (`feat`, `fix`, `chore`, `test`, `docs`, `refactor`, `perf`).
- Branch naming: `feat/<phase>-<task>` (e.g. `feat/2-api-photos-store`).
- One PR per task. Squash merge.

---

## Before writing any class, ask:

1. **Have I read the relevant section of `DESIGN.md`?** If the class is documented there, follow the spec verbatim. If it's not, stop — should it be?
2. **What is this class's single reason to change?** If you can't answer in one sentence, it's doing too much.
3. **Does an existing service/contract already cover this?** Check `app/Contracts/`, `app/Services/`, `app/Actions/`, `app/Queries/` before creating new files.
4. **Should this depend on a contract instead of a concrete?** If a unit test would want to fake it, it needs a contract.
5. **Where will this class be tested?** If you can't name the test file before writing the class, you're writing untested code.
6. **Is there a Laravel-built-in or first-party package that already does this?** No NIH (Not Invented Here) for solved problems.
7. **Will this class touch a magnet file (see Parallelization rules)?** If yes, queue the magnet edit as a separate atomic task.
8. **Am I about to call `$request->all()`?** Stop. Use `$request->validated()`.
9. **Am I about to write a SQL query string?** Use the query builder or scopes; raw SQL only with explicit justification in the commit body.
10. **Am I about to add a new env var?** Add it to `.env.example` AND `Appendix A` of DESIGN.md in the same commit.

---

## When stuck

- **Ambiguity in REQUIREMENTS.md:** ask the user; don't invent acceptance criteria.
- **Conflict between REQUIREMENTS.md and DESIGN.md:** REQUIREMENTS wins; DESIGN gets a `spec:` PR to align.
- **Test fails after refactor:** revert, never `.skip`. (DESIGN.md §15.5)
- **Unclear if a task can parallelize:** read DESIGN.md §14.4 (magnet files); if uncertain, run sequentially.
- **Need a new pattern not in DESIGN.md:** propose it as a `docs:` PR before implementing.
