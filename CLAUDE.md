# CLAUDE.md — PhotoGallery Pro

> Project memory for Claude Code. Loaded every session. The authoritative source for *what* to build is `SPEC.md` at the repo root — this file is *how* to work in the repo.

---

## Project overview

PhotoGallery Pro is a full-stack photo gallery: users upload photos (JPG/PNG/WebP, ≤10 MB), organize them into albums, tag them, and browse via a masonry-grid React SPA with lightbox + keyboard navigation. The backend is a Laravel 13 REST API under `/api/v1` with a Filament v5 admin panel mounted at `/admin`. Image resizing and EXIF extraction run as queued background jobs so uploads return immediately. Same code runs locally (DB queue + local disk) and in production (SQS + S3) — only env vars change.

---

## Architecture

```
laravelapp/
├── SPEC.md            ← single source of truth — read first, every session
├── CLAUDE.md          ← this file
├── backend/           ← Laravel 13 API + Filament admin (PHP 8.3+)
│   ├── app/{Models,Http,Jobs,Services,Filament,Observers}
│   ├── routes/api.php (prefix /v1)
│   ├── database/migrations
│   └── tests/{Feature,Unit}        ← Pest
└── frontend/          ← React 19 SPA (Node 20+)
    ├── src/{api,components,hooks,pages,data,types}
    ├── tests/        ← Vitest
    └── e2e/          ← Playwright
```

Two deployable artifacts. They communicate via JSON. Tokens (Sanctum) are stored in `localStorage` under `pgp_token`.

---

## Critical rules

1. **`SPEC.md` is authoritative.** Before modifying any model, endpoint, resource, or component, read the relevant section. Cite the section number in commit messages when relevant.
2. **Follow data models exactly.** Column types, nullability, defaults, indexes, and FK actions are defined in `SPEC.md §4`. Do NOT invent fields. Do NOT change FK behavior. The circular FK between `albums.cover_photo_id` and `photos.id` is resolved by a dedicated migration — preserve the migration order in §3.2.
3. **Follow API contracts exactly.** Request shapes, response shapes, error codes, query parameter validation — all defined in `SPEC.md §6`. Do NOT add or rename fields. Do NOT change wrapping conventions (single-resource is `{data: ...}`; favorite-toggle and auth are unwrapped — these are intentional).
4. **Ask before deviating.** If something in the spec seems wrong or impractical, surface the conflict, propose options, wait for confirmation. Do NOT silently change behavior to "improve" it.
5. **No `tailwind.config.js`, no `postcss.config.js`, no `autoprefixer`.** Tailwind v4 with the Vite plugin only. Theme tokens go inside `@theme { ... }` in `src/index.css`. Repeat: this is non-negotiable.
6. **All file I/O goes through `Storage::disk('photos')`.** Never use `File::`, `fopen`, `copy`, or `move_uploaded_file` for photo files. The disk is swapped between `local` and `s3` via env — direct paths break production.
7. **All UI strings live in `frontend/src/data/`.** No hardcoded English strings inside JSX. Components import from `data/copy.ts`.
8. **UUIDs everywhere.** Every primary key uses `HasUuids`. Stored as `CHAR(36)`. Never use auto-increment integers for application tables.
9. **Tests run before commits.** `php artisan test` (backend) and `npm test -- --run` (frontend) must be green. Hooks enforce formatting; tests are on you.

---

## Backend stack

| Component | Version | Notes |
|---|---|---|
| PHP | 8.5+ | strict types where practical |
| Laravel | 13.x | new app skeleton |
| Filament | 5.x | mounted at `/admin`, `web` guard |
| Sanctum | 4.x | **token mode**, not stateful — tokens in `Authorization: Bearer` |
| Intervention Image | 3.x | via `intervention/image-laravel` |
| Pest | 3.x | feature + unit tests |

### Install commands

```bash
# from repo root
composer create-project laravel/laravel backend "^13.0"
cd backend
composer require filament/filament "^5.0" \
                 intervention/image-laravel "^3.0" \
                 laravel/sanctum "^4.0" \
                 league/flysystem-aws-s3-v3 "^3.0"
composer require --dev laravel/pint rector/rector pestphp/pest

# publish required Laravel migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan queue:table
php artisan queue:failed-table
php artisan queue:batches-table

# IMPORTANT: edit the Sanctum migration so tokenable_id is CHAR(36) (matches users.id)
# See SPEC.md §4.6
```

Run locally:
```bash
php artisan serve                           # API + Filament at :8000
php artisan queue:work --tries=3 --timeout=120   # in a second terminal
```

---

## Frontend stack

| Component | Version | Notes |
|---|---|---|
| Node | 20 LTS+ | |
| React | 19.x | function components only |
| Vite | 6.x | with `@vitejs/plugin-react` |
| Tailwind CSS | **4.x** | via `@tailwindcss/vite` — see below |
| React Router | 7.x | data router API |
| TanStack Query | 5.x | server state, caching, polling |
| Axios | 1.x | with auth + error interceptors |
| Vitest + RTL | latest | |
| Playwright | latest | E2E |

### Install commands

```bash
# from repo root
npm create vite@latest frontend -- --template react-ts
cd frontend

npm install react-router-dom@^7 \
            @tanstack/react-query@^5 \
            axios@^1 \
            lucide-react \
            sonner

npm install -D @tailwindcss/vite tailwindcss@^4 \
               vitest \
               @testing-library/react @testing-library/user-event @testing-library/jest-dom jsdom \
               @playwright/test \
               prettier eslint @typescript-eslint/eslint-plugin @typescript-eslint/parser eslint-plugin-react-hooks
```

### Tailwind v4 setup — DO THIS EXACTLY

`vite.config.ts`:
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

`src/index.css`:
```css
@import "tailwindcss";

@theme {
  --color-brand-500: oklch(0.65 0.2 270);
  --font-sans: "Inter", system-ui, sans-serif;
}
```

**Forbidden in this project:**
- `tailwind.config.js` — Tailwind v4 does not need it
- `postcss.config.js` — `@tailwindcss/vite` handles PostCSS internally
- `autoprefixer` — built into the Tailwind v4 engine

If you find yourself typing any of those filenames, stop and re-read this section.

Run locally:
```bash
npm run dev            # Vite at :5173, proxies /api to Laravel
npm run build          # production bundle to dist/
npm test -- --run      # Vitest
npx playwright test    # E2E
```

---

## Image processing conventions

- **Library:** Intervention Image **v3** only. Driver: GD or Imagick (whichever the host provides — config in `config/image.php`).
- **Wrapper service:** `App\Services\ImageProcessor::generate(Photo $photo)` is the single entry point. Controllers and admin actions never call Intervention directly.
- **Sizes** (`SPEC.md §11.2`):

  | Size | Width | Quality | Format |
  |---|---|---|---|
  | thumbnail | 300 px | 80 | JPEG |
  | medium    | 800 px | 85 | JPEG |
  | large     | 1600 px | 90 | JPEG |

- **Never upscale.** If the source width is smaller than the target, copy the source as that size.
- **Auto-orient** from EXIF, **strip metadata** from the resized output (originals retain it).
- **All file ops use `Storage::disk('photos')`.** Path layout:
  ```
  photos/originals/{uuid}.{ext}
  photos/thumbnails/{uuid}.jpg
  photos/medium/{uuid}.jpg
  photos/large/{uuid}.jpg
  ```
- **EXIF extraction:** `App\Services\ExifExtractor::extract($absolutePath)` — returns `[]` on missing/unreadable, never throws.
- **Job:** `App\Jobs\ProcessPhoto implements ShouldQueue` — `$tries = 3`, `$backoff = [10, 30, 60]`. `failed()` writes `processing_status='failed'` and `processing_error`.
- **Batch:** `POST /photos/batch` wraps multiple `ProcessPhoto` dispatches in `Bus::batch(...)`. Polling endpoint reads `Bus::findBatch($id)`.

---

## Queue & storage configuration

Same code, different env. `config/filesystems.php` defines a `photos` disk that resolves to `local` or `s3` based on `PHOTOS_DISK`. `QUEUE_CONNECTION` swaps between `database` and `sqs`. **Never hardcode disk or queue names — always read env / use `Storage::disk('photos')`.**

| Env var | Local dev | Production |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | `sqs` |
| `FILESYSTEM_DISK` | `photos` | `photos` |
| `PHOTOS_DISK` | `public` | `s3` |
| `SQS_KEY` / `SQS_SECRET` / `SQS_PREFIX` / `SQS_QUEUE` / `SQS_REGION` | — | required |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` / `AWS_URL` | — | required |
| `SANCTUM_TOKEN_EXPIRATION` | `null` (or 30d) | `43200` (30 days, in minutes) |

Local worker:
```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Production worker (under Supervisor):
```bash
php artisan queue:work sqs --queue=photogallerypro --tries=3 --timeout=120 --sleep=3
```

When migrating to AWS:
- Edit `.env` only — no code changes.
- S3 bucket needs CORS config allowing the production frontend origin.
- SQS queue is **standard**, not FIFO.
- Run `backend/scripts/smoke-aws.php` before flipping production traffic.

---

## Code conventions

### PHP (backend)
- **Formatter:** Laravel Pint (`backend/pint.json`, Laravel preset). Auto-runs via Claude Code `PostToolUse` hook on `*.php` files. Pre-commit via lefthook.
- **Modernization:** Rector with `withPhpSets(php83: true)`.
- **Tests:** Pest 3.x. Use `Storage::fake('photos')` and `Queue::fake()` in feature tests. TDD encouraged for jobs and services.
- **UUIDs:** every Eloquent model that ships data uses `use HasUuids;`. Never auto-increment.
- **Enums:** PHP 8.3 backed enums for status fields (`App\Enums\ProcessingStatus`).
- **Strict types:** `declare(strict_types=1);` at the top of `app/Services/*` and `app/Jobs/*`.
- **No facades in models.** Inject services through the IoC container.

### TypeScript / React (frontend)
- **Formatter:** Prettier (`.prettierrc`: `singleQuote: true`, `semi: true`, `printWidth: 100`).
- **Linter:** ESLint with `@typescript-eslint` + `react-hooks`.
- **Components:** function components only, named exports preferred.
- **Hooks:** custom hooks live in `src/hooks/`, one hook per file, prefixed `use*`.
- **API access:** never call `axios` directly from a component — go through `src/api/{photos,albums,tags,auth}.ts` wrappers + `src/hooks/use*` for caching.
- **Server state:** TanStack Query. Local UI state: `useState`/`useReducer`. No Redux, no Zustand in v1.
- **Strings:** every user-facing string lives in `src/data/copy.ts` (or `nav.ts` / `shortcuts.ts`). PRs that introduce hardcoded strings must be rewritten.
- **Tests:** Vitest + React Testing Library; user-event for interactions; jsdom environment.

### Git
- **Branch naming:** `feat/<topic>`, `fix/<topic>`, `chore/<topic>`, `test/<topic>`, `docs/<topic>`.
- **Commits:** Conventional Commits (`feat(scope): ...`, `fix(scope): ...`). One logical change per commit.
- **PRs:** every PR fills the template in `.github/PULL_REQUEST_TEMPLATE.md` — Summary, Screenshots (frontend), Test plan, Spec references.
- **Direct push to `main` is blocked** by branch protection. Open a PR.
- **Hooks:** `lefthook` runs Pint + Prettier + ESLint pre-commit. Don't bypass with `--no-verify`.

### Spec changes
- Changes to `SPEC.md` are first-class work. Open a PR titled `spec: ...` and update CLAUDE.md if a convention changes. The spec and code stay in lockstep — drift is a bug.
