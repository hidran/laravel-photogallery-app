# Spec Coverage Audit

Generated: 2026-04-30

## Summary
- Total sections: 42 (subsections of DESIGN.md)
- Implemented: 27 (✅)
- Partial: 11 (⚠️)
- Missing: 4 (❌)

## Coverage Matrix

### Section 1 — Architecture overview

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §1 | Architecture overview (monorepo layout) | `backend/`, `frontend/`, `docs/` | — | ✅ |

### Section 2 — Tech stack

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §2 | Tech stack (dependencies) | `backend/composer.json`, `frontend/package.json` | — | ✅ |

### Section 3 — Folder / file architecture

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §3.1 | Monorepo root | `CLAUDE.md`, `docs/` | — | ✅ |
| §3.2 | Backend directory structure | `backend/app/` | — | ⚠️ |
| §3.3 | Frontend directory structure | `frontend/src/` | — | ⚠️ |

**§3.2 details — missing files vs spec:**
- `app/Actions/Photo/UpdatePhotoAction.php` — **missing** (update logic inlined in controller)
- `app/Actions/Photo/MarkFavoriteAction.php` — **missing**
- `app/Actions/Photo/UnmarkFavoriteAction.php` — **missing**
- `app/Actions/Album/CreateAlbumAction.php` — **missing**
- `app/Actions/Album/UpdateAlbumAction.php` — **missing**
- `app/Actions/Album/DeleteAlbumAction.php` — **missing**
- `app/Providers/AuthServiceProvider.php` — **missing** (policies registered in `AppServiceProvider` or auto-discovered)
- `app/Filament/Resources/Photos/Pages/ViewPhoto.php` — **missing**
- `tests/Doubles/FakeImageProcessor.php` — **missing** (tests use Storage::fake or inline mocks)
- `tests/Doubles/FakeExifExtractor.php` — **missing**
- `database/seeders/Test/PhotoSeeder.php` — **missing** (seeders at root: `PhotoSeeder.php`, `TagSeeder.php`)
- `database/seeders/Test/TagSeeder.php` — **missing**
- `scripts/smoke-aws.php` — **missing**
- `.env.production.example` — **missing**

**§3.2 — present but different path from spec:**
- Filament resources use subdirectory grouping (e.g., `Resources/Photos/PhotoResource.php` instead of `Resources/PhotoResource.php`) — functionally equivalent.

**§3.3 details — missing files vs spec:**
- `src/components/common/Button.tsx` — **missing** (no `common/` subdirectory; components are flat)
- `src/components/common/EmptyState.tsx` — **missing**
- `src/components/common/Spinner.tsx` — **missing**
- `src/lib/formatBytes.ts` — **missing**
- `src/hooks/useBatchPoll.ts` — **missing** (batch polling may be in `useUpload.ts` or `useUploadFlow.ts`)
- Frontend component subdirectories (`common/`, `gallery/`, `layout/`, `upload/`, `providers/`) are **not used** — all components are flat under `src/components/`.
- `frontend/tests/` and `frontend/e2e/` directories — **missing** (test files are colocated: `*.test.tsx` next to source files)

### Section 4 — Database schema

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §4.1 | `users` table | `0001_01_01_000000_create_users_table.php` | `AuthControllerTest.php` | ✅ |
| §4.2 | `albums` table | `2026_04_27_160051_create_albums_table.php` | `AlbumControllerTest.php` | ✅ |
| §4.3 | `tags` table | `2026_04_27_160052_create_tags_table.php` | `TagControllerTest.php` | ✅ |
| §4.4 | `photos` table | `2026_04_27_160204_create_photos_table.php` | `PhotoControllerCrudTest.php` | ✅ |
| §4.5 | `photo_tag` pivot | `2026_04_27_160421_create_photo_tag_table.php` | `TagAssignerTest.php` | ✅ |
| §4.6 | `favorites` pivot | `2026_04_30_135152_create_favorites_table.php` | `PhotoControllerFavoriteTest.php`, `FavoritesFeatureTest.php` | ✅ |
| §4.7 | `personal_access_tokens` | `2026_04_27_155018_create_personal_access_tokens_table.php` | `SanctumTokenTest.php` | ✅ |
| §4.8 | Queue tables (`jobs`, `failed_jobs`, `job_batches`) | `0001_01_01_000002_create_jobs_table.php` | — | ✅ |

**Note:** `add_cover_photo_fk_to_albums` migration exists at `2026_04_27_160336_add_cover_photo_fk_to_albums.php` — matches spec.

### Section 5 — Eloquent relationships

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §5 | Model relationships (Photo, Album, Tag, User) | `app/Models/{Photo,Album,Tag,User}.php` | Various feature tests | ✅ |

### Section 6 — API contracts

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §6.0 | Conventions (envelope, cursor pagination, rate limits, ETag) | `app/Http/Middleware/{ETag,ForceJsonResponse}.php`, `routes/api.php` | `ETagTest.php`, `ForceJsonResponseTest.php` | ✅ |
| §6.1 | Auth endpoints (register, login, logout, me) | `app/Http/Controllers/Api/V1/AuthController.php` | `AuthControllerTest.php`, `SanctumTokenTest.php` | ✅ |
| §6.2 | Photo endpoints (CRUD + favorite + batch) | `app/Http/Controllers/Api/V1/PhotoController.php`, `BatchController.php` | `PhotoControllerCrudTest.php`, `PhotoControllerIndexTest.php`, `PhotoControllerFavoriteTest.php` | ✅ |
| §6.3 | Album endpoints | `app/Http/Controllers/Api/V1/AlbumController.php` | `AlbumControllerTest.php` | ✅ |
| §6.4 | Tag endpoints | `app/Http/Controllers/Api/V1/TagController.php` | `TagControllerTest.php` | ✅ |
| §6.5 | Health endpoint | `app/Http/Controllers/Api/V1/HealthController.php` | `HealthControllerTest.php` | ✅ |
| §6.6 | Resource shapes (PhotoData, AlbumData, TagData, UserData) | `app/Http/Resources/{PhotoData,AlbumData,TagData,UserData}.php` | `PhotoDataTest.php` | ⚠️ |
| §6.7 | Error matrix | `bootstrap/app.php` | `ApiErrorEnvelopeTest.php`, `AuthorizationTest.php` | ✅ |
| §6.8 | Backend conventions (route model binding, validated(), transactions) | Various controllers and requests | Various feature tests | ✅ |

**§6.6 note:** Resource shape tests only cover `PhotoData`. No dedicated tests for `AlbumData`, `TagData`, or `UserData` resource output shapes.

### Section 7 — Filament admin

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §7.1 | PhotoResource | `app/Filament/Resources/Photos/` | `FilamentCreatePhotoTest.php`, `FilamentSmokeTest.php` | ⚠️ |
| §7.2 | AlbumResource | `app/Filament/Resources/Albums/` | `AdminPanelTest.php` | ⚠️ |
| §7.3 | TagResource | `app/Filament/Resources/Tags/` | `AdminPanelTest.php` | ⚠️ |
| §7.4 | Widgets (StatsOverview, RecentUploads, QueueMonitor) | `app/Filament/Widgets/{StatsOverview,RecentUploadsTable,QueueMonitor}.php` | `FilamentSmokeTest.php` | ✅ |
| §7.5 | StorageManagement page | `app/Filament/Pages/StorageManagement.php` | — | ⚠️ |

**§7.1 note:** `ViewPhoto.php` page is missing per spec. ReprocessAction, bulk actions (AssignToAlbumBulkAction, ToggleFavoriteBulkAction, ReprocessBulkAction) not verified as present.
**§7.5 note:** File exists but no dedicated test.

### Section 8 — Frontend components

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §8.0 | Frontend conventions (strict TS, error boundaries) | `tsconfig.app.json`, `ErrorBoundary.tsx` | `ErrorBoundary.test.tsx` | ✅ |
| §8.1 | App shell + routing | `App.tsx`, `main.tsx` | — | ✅ |
| §8.2 | Navbar | `components/Navbar.tsx` | — | ⚠️ |
| §8.3 | Sidebar | `components/Sidebar.tsx` | — | ⚠️ |
| §8.4 | MasonryGrid | `components/MasonryGrid.tsx` | — | ⚠️ |
| §8.5 | PhotoCard | `components/PhotoCard.tsx` | — | ⚠️ |
| §8.6 | PhotoLightbox | `components/PhotoLightbox.tsx` | — | ⚠️ |
| §8.7 | UploadModal + DropZone | `components/UploadModal.tsx`, `components/DropZone.tsx` | — | ⚠️ |
| §8.8 | Keyboard shortcuts | `components/KeyboardShortcutsProvider.tsx`, `hooks/useKeyboardShortcuts.ts` | `useKeyboardShortcuts.test.ts` | ✅ |
| §8.9 | Data module | `data/{copy,nav,polling,shortcuts}.ts` | — | ✅ |
| §8.10 | API client | `api/client.ts`, `api/{photos,albums,tags,batch,auth}.ts` | — | ✅ |
| §8.11 | Vite config | `vite.config.ts` | — | ✅ |
| §8.12 | Tailwind v4 setup | `index.css` (`@import "tailwindcss"`, `@theme`, reduced-motion) | — | ✅ |

**§8.2–§8.7 note:** Components exist but no dedicated tests (spec lists `tests/components/PhotoLightbox.test.tsx`, `tests/components/UploadModal.test.tsx` which are missing as standalone files). Colocated `Modal.test.tsx` exists.
**Missing common components:** `Button.tsx`, `EmptyState.tsx`, `Spinner.tsx` (spec §3.3) are not present as separate files.
**Missing hook:** `useBatchPoll.ts` — batch polling logic may be embedded in `useUploadFlow.ts`.
**Missing utility:** `lib/formatBytes.ts`.

### Section 9 — Service interfaces (DIP)

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §9.1 | `ImageProcessor` contract | `app/Contracts/ImageProcessor.php`, `app/Services/Imaging/InterventionImageProcessor.php` | `InterventionImageProcessorTest.php` | ✅ |
| §9.2 | `ExifExtractor` contract | `app/Contracts/ExifExtractor.php`, `app/Services/Imaging/PhpExifExtractor.php` | `PhpExifExtractorTest.php` | ✅ |
| §9.3 | `PhotoStorage` contract | `app/Contracts/PhotoStorage.php`, `app/Services/Storage/DiskPhotoStorage.php` | `DiskPhotoStorageTest.php` | ✅ |
| §9.4 | Bindings in AppServiceProvider | `app/Providers/AppServiceProvider.php` | — | ✅ |

**Note:** Test doubles (`tests/Doubles/FakeImageProcessor.php`, `tests/Doubles/FakeExifExtractor.php`) are **missing** per spec. Tests use inline mocks or `Storage::fake()` instead.

### Section 10 — Authentication

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §10.1 | Filament admin auth (session/web guard) | `app/Providers/Filament/AdminPanelProvider.php` | `AdminPanelTest.php` | ✅ |
| §10.2 | Sanctum token mode (24h TTL) | `config/sanctum.php` | `SanctumTokenTest.php` | ✅ |
| §10.3 | Token abilities (photos:write, albums:write, admin) | `app/Enums/TokenAbility.php`, `AuthController.php` | `AuthorizationTest.php` | ✅ |
| §10.4 | CORS | `config/cors.php` | `CorsTest.php` | ✅ |
| §10.5 | XSS / token storage trade-off | Documentation only | — | ✅ |

### Section 11 — Error handling

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §11.1 | API error envelope | `bootstrap/app.php`, `app/Http/Middleware/ForceJsonResponse.php` | `ApiErrorEnvelopeTest.php` | ✅ |
| §11.2 | Per-scenario status codes | `bootstrap/app.php` | `AuthorizationTest.php` | ✅ |
| §11.3 | Frontend error UX | `components/ErrorBoundary.tsx`, `components/ProcessingOverlay.tsx` | `ErrorBoundary.test.tsx` | ⚠️ |

**§11.3 note:** Error boundary exists and is tested, but toast integration (sonner) and inline validation on 422 not verified as fully wired up.

### Section 12 — Image processing pipeline

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §12.1 | Two-disk model | `config/filesystems.php` (`photos`, `photos_private` disks) | `DiskPhotoStorageTest.php` | ✅ |
| §12.2 | Upload flow | `app/Actions/Photo/UploadPhotosAction.php` | `PhotoControllerCrudTest.php` | ✅ |
| §12.3 | `ProcessPhoto` job | `app/Jobs/ProcessPhoto.php` | `ProcessPhotoJobTest.php` | ✅ |
| §12.4 | Batch tracking | `app/Http/Controllers/Api/V1/BatchController.php` | — | ⚠️ |
| §12.5 | Env switching (local vs S3) | `config/filesystems.php` | — | ✅ |
| §12.6 | Workers | Documentation / `.env.example` | — | ✅ |
| §12.7 | Logging (processing channel) | — | — | ❌ |

**§12.4 note:** `BatchController` exists but no dedicated `BatchProgressTest.php` (spec lists one).
**§12.7 note:** No dedicated `processing` log channel found in `config/logging.php`.

### Section 13 — Dev tooling (Appendix A)

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §Appendix A | `config/photogallery.php` | `backend/config/photogallery.php` | — | ✅ |
| §Appendix A | `frontend/src/data/polling.ts` | `frontend/src/data/polling.ts` | — | ✅ |
| — | `pint.json` | `backend/pint.json` | — | ✅ |
| — | `rector.php` | `backend/rector.php` | — | ✅ |
| — | `.env.example` | `backend/.env.example` | — | ✅ |
| — | `.env.production.example` | — | — | ❌ |
| — | `lefthook.yml` | — | — | ❌ |
| — | `scripts/smoke-aws.php` | — | — | ❌ |

### Section 14 — Parallelization

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §14 | Parallelization plan | Documentation only (CLAUDE.md) | — | ✅ |

### Section 15 — Testing

| Section | Description | Code Location | Test Location | Status |
|---------|------------|---------------|---------------|--------|
| §15 | Test file inventory | `backend/tests/` | — | ⚠️ |

**§15 details — spec vs actual test files:**

| Spec test file | Actual file | Status |
|----------------|-------------|--------|
| `Feature/Api/V1/AuthTest.php` | `Feature/Api/V1/AuthControllerTest.php` | ✅ (renamed) |
| `Feature/Api/V1/AuthorizationTest.php` | `Feature/Api/V1/AuthorizationTest.php` | ✅ |
| `Feature/Api/V1/PhotoIndexTest.php` | `Feature/Api/V1/PhotoControllerIndexTest.php` | ✅ (renamed) |
| `Feature/Api/V1/PhotoCrudTest.php` | `Feature/Api/V1/PhotoControllerCrudTest.php` | ✅ (renamed) |
| `Feature/Api/V1/PhotoUploadTest.php` | — | ❌ (upload tested in PhotoControllerCrudTest) |
| `Feature/Api/V1/PhotoFavoriteTest.php` | `Feature/Api/V1/PhotoControllerFavoriteTest.php` | ✅ (renamed) |
| `Feature/Api/V1/BatchProgressTest.php` | — | ❌ |
| `Feature/Api/V1/AlbumCrudTest.php` | `Feature/Api/V1/AlbumControllerTest.php` | ✅ (renamed) |
| `Feature/Api/V1/TagIndexTest.php` | `Feature/Api/V1/TagControllerTest.php` | ✅ (renamed) |
| `Feature/Api/V1/HealthTest.php` | `Feature/Api/V1/HealthControllerTest.php` | ✅ (renamed) |
| `Feature/Jobs/ProcessPhotoTest.php` | `Feature/ProcessPhotoJobTest.php` | ✅ (different path) |
| `Unit/Queries/PhotoQueryTest.php` | `Unit/Queries/PhotoQueryTest.php` | ✅ |
| `Unit/Services/ImageProcessorTest.php` | `Unit/Services/InterventionImageProcessorTest.php` | ✅ (renamed) |
| `Unit/Services/TagAssignerTest.php` | `Unit/Services/TagAssignerTest.php` | ✅ |
| `tests/Doubles/FakeImageProcessor.php` | — | ❌ |
| `tests/Doubles/FakeExifExtractor.php` | — | ❌ |

**Additional tests not in spec (bonus):**
- `Feature/AdminPanelTest.php`
- `Feature/Auth/SanctumTokenTest.php`
- `Feature/CorsTest.php`
- `Feature/Exceptions/ApiErrorEnvelopeTest.php`
- `Feature/FavoritesFeatureTest.php`
- `Feature/FilamentCreatePhotoTest.php`
- `Feature/FilamentSmokeTest.php`
- `Feature/Middleware/ETagTest.php`
- `Feature/Middleware/ForceJsonResponseTest.php`
- `Feature/Observers/PhotoObserverTest.php`
- `Feature/Resources/PhotoDataTest.php`
- `Unit/Concerns/HasUuidV7Test.php`
- `Unit/Policies/PolicyTest.php`
- `Unit/Requests/AlbumRequestsTest.php`
- `Unit/Requests/AuthRequestsTest.php`
- `Unit/Requests/PhotoRequestsTest.php`
- `Unit/Services/DiskPhotoStorageTest.php`
- `Unit/Services/PhpExifExtractorTest.php`

---

## Gap Summary

### Missing code (spec says exists, codebase does not have it)

| Item | DESIGN.md reference | Priority |
|------|---------------------|----------|
| `app/Actions/Photo/UpdatePhotoAction.php` | §3.2, §6.8 | Medium |
| `app/Actions/Photo/MarkFavoriteAction.php` | §3.2 | Low (logic in controller) |
| `app/Actions/Photo/UnmarkFavoriteAction.php` | §3.2 | Low (logic in controller) |
| `app/Actions/Album/CreateAlbumAction.php` | §3.2 | Medium |
| `app/Actions/Album/UpdateAlbumAction.php` | §3.2 | Medium |
| `app/Actions/Album/DeleteAlbumAction.php` | §3.2 | Medium |
| `app/Filament/Resources/Photos/Pages/ViewPhoto.php` | §3.2, §7.1 | Low |
| `tests/Doubles/FakeImageProcessor.php` | §3.2, §9.1 | Low |
| `tests/Doubles/FakeExifExtractor.php` | §3.2, §9.2 | Low |
| `database/seeders/Test/PhotoSeeder.php` | §3.2 | Low |
| `database/seeders/Test/TagSeeder.php` | §3.2 | Low |
| `scripts/smoke-aws.php` | §3.2 | Low (Phase 8) |
| `.env.production.example` | §3.2, §12.5 | Medium (Phase 8) |
| `lefthook.yml` | §13 | Medium |
| Dedicated `processing` log channel | §12.7 | Low |
| `app/Providers/AuthServiceProvider.php` | §3.2 | Low (auto-discovery) |

### Missing frontend files

| Item | DESIGN.md reference | Priority |
|------|---------------------|----------|
| `components/common/Button.tsx` | §3.3 | Medium |
| `components/common/EmptyState.tsx` | §3.3 | Medium |
| `components/common/Spinner.tsx` | §3.3 | Medium |
| `lib/formatBytes.ts` | §3.3 | Low |
| `hooks/useBatchPoll.ts` | §3.3, §8.7 | Medium |
| Component subdirectory structure (`common/`, `gallery/`, `layout/`, `upload/`, `providers/`) | §3.3 | Low (cosmetic) |

### Missing tests

| Item | DESIGN.md reference | Priority |
|------|---------------------|----------|
| `Feature/Api/V1/BatchProgressTest.php` | §3.2 | High |
| `Feature/Api/V1/PhotoUploadTest.php` | §3.2 | Medium (covered elsewhere) |
| Frontend `tests/components/PhotoLightbox.test.tsx` | §3.3 | Medium |
| Frontend `tests/components/UploadModal.test.tsx` | §3.3 | Medium |
| Frontend `tests/hooks/useUpload.test.ts` | §3.3 | Medium |
| Frontend `e2e/*.spec.ts` | §3.3 | Low (Phase 10+) |
| `StorageManagement` page test | §7.5 | Low |

### Structural divergences (working but different from spec)

| Divergence | Spec says | Actual |
|-----------|-----------|--------|
| Filament resource paths | `Resources/PhotoResource.php` | `Resources/Photos/PhotoResource.php` (grouped) |
| Test file names | `AuthTest.php`, `PhotoCrudTest.php` | `AuthControllerTest.php`, `PhotoControllerCrudTest.php` |
| Frontend component organization | Subdirectories (`common/`, `gallery/`) | Flat under `components/` |
| Frontend test location | `frontend/tests/` directory | Colocated `*.test.tsx` files next to source |
| Actions pattern | All CRUD via Action classes | Only `UploadPhotosAction` exists; rest inlined in controllers |
| `AuthServiceProvider` | Separate file | Merged into `AppServiceProvider` or auto-discovered |
