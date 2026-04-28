# Code review fixes — log

> Lasting record of issues surfaced by code reviews on this project and how they were fixed. Each entry cites the PR, the severity, the original finding, the fix commit, and the regression test that locks the bug from coming back.

---

## How to read this file

Every section is one PR's review pass. Findings are tagged:

- **`[blocker]`** — must be fixed before merge; correctness or security bug with attack surface.
- **`[high]`** — security issue with realistic exploit path.
- **`[major]`** — spec drift, principles violation, or correctness bug with limited blast radius.
- **`[medium]`** — security issue requiring specific deployment conditions.
- **`[low]`** — security improvement worth tracking but not currently exploitable.
- **`[minor]`** / **`[nit]`** — code quality, no behavior change.

Each finding row carries **what was wrong**, the **fix**, and a **regression test** so future maintainers can see at a glance whether a change reopens the issue.

---

## PR #1 — Phase 1 backend foundation (T001–T018)

Branch: `feat/phase-1-backend-foundation` → merged to `main` as commit `25bb236`.

Reviewed by `superpowers:code-reviewer` after the initial 21 commits. The user separately caught one Sanctum bug before the review ran (logged below as F0).

### F0 — Sanctum `personal_access_tokens.id` was changed to UUID
**Severity:** blocker • **Caught by:** user, mid-review • **Fix commit:** `fb39632` *(fix(backend): keep Sanctum's auto-increment PK on personal_access_tokens)*

**File:** `backend/database/migrations/2026_04_27_155018_create_personal_access_tokens_table.php`

**Issue.** Initial T003 over-reached: I changed both `tokenable_id` (per DESIGN §4.6) AND the table's PK to UUID. Sanctum's `HasApiTokens::createToken()` only inserts `name`, `token`, `abilities`, and `expires_at` — it never supplies an id. The default `Laravel\Sanctum\PersonalAccessToken` model uses an auto-increment integer PK, so a UUID PK with no DB default would NOT-NULL-fail on the very first `$user->createToken(...)` call.

**Fix.** Reverted PK to `$table->id()` (auto-increment). Kept `uuidMorphs('tokenable')` since DESIGN §4.6 only requires `tokenable_id` to be CHAR(36).

**Regression test.** `tests/Feature/Auth/SanctumTokenTest.php` — `createToken()` with abilities, asserts plain-text token + `tokenable_id` round-trip + ability grant/deny.

---

### F1 — `photos.user_id` had no index
**Severity:** blocker • **Fix commit:** `5f03e4a` *(fix(backend): add missing FK indexes and wire Sanctum 24h TTL)*

**File:** `backend/database/migrations/2026_04_27_160204_create_photos_table.php`

**Issue.** `foreignUuid('user_id')->constrained()` does NOT auto-create the supporting index on SQLite or PostgreSQL. DESIGN §4.4 lists `INDEX (user_id)` first; §4 conventions state "every FK column has an index." `WHERE user_id = ?` would table-scan in production without it.

**Fix.** Added `$table->index('user_id', 'photos_user_idx')`.

**Regression test.** Empirical: `Schema::getIndexes('photos')` returns `photos_user_idx`. Visible in the migrate:fresh output during Phase 1 review.

---

### F2 — `photo_tag` pivot missing `INDEX (tag_id)`
**Severity:** major • **Fix commit:** `5f03e4a`

**File:** `backend/database/migrations/2026_04_27_160421_create_photo_tag_table.php`

**Issue.** Composite PK `(photo_id, tag_id)` is leftmost-prefix only. It covers "tags for this photo" but not the reverse `WHERE tag_id = ?` lookup that `Tag::photos()` and the `?tags[]=` filter both need. DESIGN §4.5 explicitly requires the index.

**Fix.** Added `$table->index('tag_id', 'photo_tag_tag_idx')`.

**Regression test.** Empirical via `Schema::getIndexes('photo_tag')`.

---

### F3 — `SANCTUM_TOKEN_EXPIRATION` env var advertised but unread
**Severity:** major • **Fix commit:** `5f03e4a`

**File:** `backend/config/sanctum.php`

**Issue.** `.env.example` documented `SANCTUM_TOKEN_EXPIRATION=1440` (24h) per DESIGN §10.2, but `config/sanctum.php` was hard-coded to `'expiration' => null`. Issued tokens never expired despite the docs claiming 24h TTL.

**Fix.** `'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 1440)`.

**Regression test.** `SanctumTokenTest` verifies `config('sanctum.expiration')` resolves to `1440` and `AuthController::issueToken` passes the computed `$expiresAt` into `createToken()`.

---

### F4 — `final` missing on Phase 1 framework classes
**Severity:** major • **Fix commit:** `78c9e24` *(style(backend): final classes, strict_types on UserFactory, Pest cleanup)*

**Files:** `AppServiceProvider`, `DatabaseSeeder`, all 4 factories.

**Issue.** CLAUDE.md "Code style → PHP" mandates "final by default." Models were already `final`; framework classes were not.

**Fix.** Added `final` to `AppServiceProvider`, `DatabaseSeeder`, `UserFactory`, `PhotoFactory`, `AlbumFactory`, `TagFactory`. `User` stays non-final (Authenticatable extension is the documented Laravel idiom).

**Regression test.** Pint enforces no rule for this directly; relies on convention + reviews. Could add a Pest "arch" assertion in Phase 10 if drift recurs.

---

### F5 — `declare(strict_types=1)` missing on `UserFactory`
**Severity:** major • **Fix commit:** `78c9e24`

**File:** `backend/database/factories/UserFactory.php`

**Issue.** Inconsistent with every other hand-written file on the branch. Pint then auto-fixed `static::$password` to `self::$password` once the class was final.

**Fix.** Added the directive.

---

### F6 — Photo `$fillable` exposes processing fields
**Severity:** minor • **Fix commit:** `75f93cd` *(docs(backend): document why processing_* are still mass-assignable)*

**File:** `backend/app/Models/Photo.php`

**Issue.** `processing_status`, `processing_attempts`, `processing_error` are job-internal state, not user input. Leaving them mass-assignable is a soft-fence concern.

**Fix.** Documented the constraint with a `$fillable` docblock comment. Removing them would force `forceFill()` everywhere; the hard fence per CLAUDE.md rule 6 is FormRequest validation, and DESIGN §6.2 already excludes these keys from every documented request.

**Regression test.** Implicit: every PhotoController test goes through `$request->validated()`, never `$request->all()`.

---

### F7 / F8 / F9 — Cosmetic
- **F7** [minor]: `User` model uses attribute-style `#[Fillable]`/`#[Hidden]` while other models use `protected $fillable`/`protected $casts()`. Inconsistent but both valid Laravel 11+. Left as-is.
- **F8** [minor]: `PhotoFactory::definition()` returns the enum's `->value` rather than the case. Works because of the cast on hydrate. Left as-is for now; convention noted.
- **F9** [nit]: `PhotoObserverTest` second case name oversold its precondition. **Fixed** in `78c9e24` — renamed.

---

## PR #4 — Phase 2 API layer (T019–T045 less upload pipeline)

Branch: `feat/phase-2-api-layer`.

Two parallel review passes: `superpowers:code-reviewer` (spec/principles/quality) + `security-auditor` (OWASP/auth/CORS/proxies). All findings address themselves in fix commit `3ece74b` *(fix(api): address PR #4 code + security review findings)*.

### S1 — PII leak via `exif` and `processing_error`
**Severity:** high (security)

**File:** `backend/app/Http/Resources/PhotoData.php:56-58`

**Issue.** Both fields were returned to **all** viewers including anonymous. EXIF can carry GPS coordinates, device serials, and lens metadata; `processing_error` may contain path fragments and queue-worker context. The `urls.original` gate that protects the signed URL was not extended to these fields.

**Attack scenario.** `GET /api/v1/photos/{id}` from an unauthenticated client returns `{ "exif": { "GPS": ... }, "processing_error": "/var/www/storage/photos-private/abc.jpg: PHP Fatal error" }` — leaks physical location and internal error context.

**Fix.** Apply the same `$isOwnerOrAdmin` predicate already used for `urls.original`:
```php
'exif' => $isOwnerOrAdmin ? $this->exif : null,
'processing_error' => $isOwnerOrAdmin ? $this->processing_error : null,
```

**Regression test.** `tests/Feature/Resources/PhotoDataTest.php` — 4 cases covering anonymous-hidden, owner-visible, admin-visible-on-others, owner-visible-with-real-data.

---

### S2 — Wildcard CORS
**Severity:** high (security)

**File:** `backend/config/cors.php` (was missing)

**Issue.** Laravel ships with a default `cors.php` template carrying `'allowed_origins' => ['*']`. The PR's `.env.example` advertised `FRONTEND_URL` for the allowlist but never published a `cors.php` to consume it — so the default wildcard policy was active.

**Attack scenario.** Any origin can issue cross-origin requests to `/api/v1/*` with the browser's same-origin protections relaxed.

**Fix.** Wrote `config/cors.php` with `paths=['api/*']`, `allowed_origins=[env('FRONTEND_URL')]`, no `*`, `supports_credentials=false` (Bearer-token model — DESIGN §10.5 — so credentialed CORS isn't needed). Exposed `ETag` + `Retry-After` so the SPA can read those headers.

**Regression test.** `tests/Feature/CorsTest.php` — 3 cases: config shape, allowlisted origin echoed in `Access-Control-Allow-Origin`, non-allowlisted origin rejected.

---

### S3 — `AlbumController@store` missing policy gate
**Severity:** medium (security)

**File:** `backend/app/Http/Controllers/Api/V1/AlbumController.php:53-55`

**Issue.** `store` checked only `$this->ensureAbility($request, TokenAbility::AlbumsWrite)` — the token-ability gate. Every other mutation across both controllers correctly dual-gates (token ability + policy method); this one slipped. CLAUDE.md rule 10 + DESIGN §6.4a require both.

**Attack scenario.** A user holding a downgraded or service-account token with `albums:write` could create albums even if a future policy refinement intended to block them — the policy was never consulted.

**Fix.** Added `AlbumPolicy::create(User $user): bool { return true; }` and `$request->user()?->cannot('create', Album::class)` check in the controller. Permissive today, but the gate now exists for tightening without controller edits.

**Regression test.** Implicit: `AlbumControllerTest` covers the create path; the dual-gate pattern is now symmetric so a future tightening of `create()` will surface in those tests.

---

### S4 — `$request->ip()` returns proxy IP behind reverse proxies
**Severity:** medium (security)

**File:** `backend/bootstrap/app.php`

**Issue.** No `trustProxies` configuration. Behind ALB/Nginx/CloudFront, every request has the same `REMOTE_ADDR` (the proxy's), so the auth rate limiter (10/min/IP via `$request->ip()`) collapses into one shared bucket — effectively a no-op against credential-stuffing in production.

**Fix.** Env-driven wiring via the new `TRUSTED_PROXIES` var:
```php
$trustedProxies = env('TRUSTED_PROXIES', '');
if ($trustedProxies !== '') {
    $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : explode(',', $trustedProxies));
}
```
`.env.example` documents the value choices: `''` (no proxy / dev), `'*'` (single-tenant ALB), CIDR/IP list (stricter setups).

**Regression test.** No automated test (proxies aren't exercised in unit-test runs); production deployment checklist is the verification path.

---

### S5 — `filename` exposed to anonymous viewers
**Severity:** low (security) • **Status:** deferred

**File:** `backend/app/Http/Resources/PhotoData.php:44`

**Issue.** Filename is returned without an ownership gate. If Phase 6 uploads use predictable or user-supplied names, this becomes an enumerable storage path.

**Why deferred.** Phase 2 doesn't have an upload pipeline; filenames in tests come from factories. Will gate at the same time as the upload code lands so the storage-naming policy and resource-gating ship together.

---

### C1 — N+1 + silent fallback in slug→name resolution
**Severity:** blocker (code)

**File:** `backend/app/Http/Controllers/Api/V1/PhotoController.php:73`

**Issue.** Inside the PATCH transaction:
```php
collect($tags ?? [])
    ->map(fn (string $slug) => Tag::where('slug', $slug)->value('name') ?? $slug)
```
Two problems: one query per slug (N round-trips inside a transaction), and `?? $slug` silently uses the slug **as a name** if the row doesn't exist. The FormRequest's `exists:tags,slug` rule prevents that today, but loosening validation later would surface ghosts in the tag table.

**Fix.** One query, no fallback:
```php
$existingNames = $tags
    ? Tag::query()->whereIn('slug', $tags)->pluck('name')->all()
    : [];
```

**Regression test.** `PhotoControllerCrudTest::PATCH replaces the tag set via TagAssigner` already proves the resolution works for valid inputs; an invalid slug now surfaces as a 422 from the FormRequest, not as a silently-created tag.

---

### C2 — Stale `updated_at` on tag-only PATCH (false 304s)
**Severity:** blocker (code)

**File:** `backend/app/Http/Controllers/Api/V1/PhotoController.php:64-67`

**Issue.** The transaction skipped `$photo->update($data)` when `$data` was empty (tag-only PATCH). Pivot writes don't bump the photo's `updated_at`, so the row's timestamp stayed stale. ETag (computed from `updated_at`) stayed stable, and the next client refetch with `If-None-Match` got a 304 even though the tag set had changed.

**Fix.** When the row wasn't updated but tags changed, `$photo->touch()` inside the transaction. ETag now invalidates exactly when the resource changes.

**Regression test.** `PhotoControllerCrudTest::PATCH bumps updated_at when only tags change` — sets `updated_at` to one hour ago, runs a tag-only PATCH, asserts the new timestamp is greater than the old.

---

### C3 — Loose N+1 budget in `PhotoControllerIndexTest`
**Severity:** major (test quality)

**File:** `backend/tests/Feature/Api/V1/PhotoControllerIndexTest.php:51`

**Issue.** Asserted `≤6` queries. Cursor pagination has no `total` query (that's offset-pagination only), so the actual budget is 1 page query + 3 eager-loads from `PhotoData::WITH` = 4. A loose threshold lets a future eager-load slip in unnoticed.

**Fix.** Tightened to `≤4`. The "no duplicate SQL" assertion is preserved as the other half of the N+1 guard.

---

### C5 — Admin-bypass assertion was too permissive
**Severity:** major (test quality)

**File:** `backend/tests/Feature/Api/V1/AuthorizationTest.php:107-108`

**Issue.** `expect($response->status())->not->toBe(403); ->not->toBe(401);` — a 5xx (unhandled exception, MethodNotAllowed) would false-pass.

**Fix.** Tightened to `toBeIn([200, 201, 204])` per DESIGN §6.7's success matrix for the actual mutation semantics under test.

---

### C4 — `AlbumController@index` doesn't go through a query object
**Severity:** major (DRY) • **Status:** deferred

**File:** `backend/app/Http/Controllers/Api/V1/AlbumController.php:24-44`

**Issue.** Builds the query inline. `PhotoQuery` exists for photos; albums should mirror it (`AlbumQuery::applySort(...)`) so Phase 3's Filament admin doesn't reimplement `match($sort)`.

**Why deferred.** Single-source-of-truth refactor without a second consumer is premature. Will extract when Phase 3 actually needs to share the logic.

---

### Minor cleanups (all in `3ece74b`)

| Tag | File | What |
|---|---|---|
| **C6** | `PhotoController.php:35` | `(bool) $request->validated('favorites')` treated string `'0'` as truthy. Switched to `$request->boolean('favorites')`. |
| **C7** | `PhotoController.php:83-85` | Dead `$photo->load(...)` immediately followed by `fresh(...)` removed. |
| **C8** | `AlbumController.php:39` | Cursor secondary sort `orderBy('id', 'desc')` ignored the primary direction; under `sort=name_asc`, same-name rows had nondeterministic tiebreak. Now tracks `$direction` so the tiebreak follows the primary. |

---

## Lessons learned

1. **Schema reviews catch silent perf cliffs.** F1, F2, S5-class issues don't fail tests on SQLite (which we use for fast tests); they only show up under MySQL/Postgres with real volume. Review the migration file against the §4 spec column-by-column AND index-by-index.
2. **`final` + `strict_types` are non-negotiable.** Pint doesn't enforce them, so review must. Phase 10 should add a Pest "arch" assertion if drift recurs (`expect(['App\Models', 'App\Services', ...])->toBeFinal()`).
3. **`shouldRenderJsonWhen` doesn't exempt every exception type.** Laravel 13's `prepareException` rewrites `AuthorizationException` → `AccessDeniedHttpException` BEFORE the render callbacks run; typehinting the original misses the conversion (caught by `assertExactJson` in PR #1, also relevant going forward).
4. **CORS defaults are `*` and Laravel doesn't ship a `cors.php` in the scaffold.** Anytime a fresh API is exposed, publishing `cors.php` is on the hard checklist — DESIGN §10.4 alone isn't enough.
5. **Reverse proxies break IP-keyed rate limiters silently.** `trustProxies` is required for any deployment with an LB in front. Add to the production deployment runbook in Phase 8.
6. **Test names should describe the invariant, not the precondition.** "doesn't blow up when X" actually verifies "Y holds when X is true" — name it Y.
7. **Spawn an independent reviewer per PR.** My own session bias missed every single issue listed here; a fresh agent with no commit-by-commit context catches more than one self-review pass would.

---

**Last updated:** PR #4 fixes pushed in `3ece74b`. Update this file with the next review's findings as they land.
