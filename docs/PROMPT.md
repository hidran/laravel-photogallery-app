I want to build a full-stack photo gallery application called PhotoGallery Pro.
I need you to generate a complete technical specification document (SPEC.md)
that I can use to build this project step by step with Claude Code.

## What the app does
A web-based photo gallery where users can:
- Upload photos (JPG, PNG, WebP, max 10MB each)
- Organize photos into albums
- Tag photos with keywords
- Search and filter by title, tags, album, favorites
- View photos in a masonry grid layout
- Open a lightbox to see full-size photos with navigation
- Mark photos as favorites
- Navigate with keyboard shortcuts
- Use an admin panel to manage everything

## Architecture
This is a full-stack app with 3 layers:
- **Backend API:** Laravel 13 (PHP 8.3+) — RESTful JSON API
- **Admin Panel:** Filament v5 — for managing photos, albums, tags, users
- **Frontend SPA:** React 19 + Vite 6 + Tailwind CSS v4 — the public gallery

The backend and frontend are separate projects:
- backend/ → Laravel application (API + Filament admin)
- frontend/ → React SPA (consumes the API)

## Database
I want these entities:
- **Photos:** id (UUID), title, description, filename, original file path,
  thumbnail/medium/large paths (for generated sizes), album association,
  width, height, file size, MIME type, favorite flag, EXIF data (JSON),
  processing status (pending/processing/completed/failed), timestamps
- **Albums:** id (UUID), name (unique), description, cover photo, timestamps
- **Tags:** id (UUID), name (unique), slug (unique auto-generated)
- **Photo-Tag pivot:** many-to-many relationship

## Image Processing Pipeline
When a photo is uploaded:
1. Save the original immediately
2. Return a response right away (don't make the user wait)
3. Queue a background job that generates 3 sizes:
   - Thumbnail: 300px wide, 80% JPEG quality
   - Medium: 800px wide, 85% quality
   - Large: 1600px wide, 90% quality
4. The job also extracts EXIF data (camera, ISO, aperture, etc.)
5. Track processing status in the database
6. If job fails, retry up to 3 times, then mark as failed

For local development: use database queue driver + local filesystem
For production: switch to AWS SQS queue + S3 storage (same code, just env vars)
Use Intervention Image v3 for image processing.

## API Endpoints
Design a RESTful API under /api/v1:
- Photos: full CRUD + toggle favorite + batch upload
  - GET index should support: search, tag filtering (AND logic),
    album filter, favorites filter, sort (newest/oldest/A-Z/favorites), pagination
- Albums: full CRUD with photo counts
- Tags: list only with photo counts
- Batch upload endpoint that accepts up to 20 files, processes as a batch job,
  returns batch ID for progress tracking
- Public routes: GET endpoints (no auth required for browsing)
- Protected routes: POST/PUT/PATCH/DELETE require Sanctum authentication

## Filament Admin Panel
Build these resources:
- PhotoResource: table with thumbnails, searchable title, album name,
  tags as badges, file size, favorite toggle, processing status badge.
  Filters for album, tag, favorites, date range, processing status.
  Bulk actions: delete, assign to album, toggle favorite, reprocess.
  Form: file upload with drag-drop, title, description, album select,
  tags multi-select with inline creation, favorite toggle.
- AlbumResource: table with name, photo count, cover thumbnail.
  Form with unique name validation. Relation manager for album's photos.
- TagResource: table with name, auto-generated slug, photo count.
- Dashboard widgets: stats overview (total photos/albums/tags/storage),
  recent uploads table, queue monitoring (pending/failed jobs).
- Storage management page: disk usage breakdown, regenerate thumbnails button.

## React Frontend Features
- Navbar: search bar (debounced 300ms), sort dropdown, upload button
- Sidebar: "All Photos", "Favorites", album list with counts, tag filters
- Gallery: masonry grid (2/3/4 columns responsive), lazy loading,
  hover overlay with title + favorite icon
- Lightbox: full-res image, left/right navigation (click + keyboard),
  edit title/description/tags inline, delete with confirm, EXIF data panel,
  "View full size" button
- Upload: drag-and-drop zone, file type validation, max 10MB,
  progress bar per file, batch up to 20 files
- Processing status: spinner overlay while pending, error state for failed
- Keyboard shortcuts: ←/→ navigate lightbox, Escape close, F favorite,
  Delete remove, / focus search
- Poll for processing completion after upload (every 3 seconds)

## Authentication
- Filament admin: standard Laravel session auth
- React frontend: Laravel Sanctum API tokens
  - Public browsing without auth
  - Auth required for upload, edit, delete, manage albums

## Error Handling
- API: 422 validation errors, 404 not found, 413 too large, 415 bad type
- Frontend: toast notifications, inline validation, empty states with CTAs

## Tech Specifics
- Tailwind CSS v4: use @tailwindcss/vite plugin, @import "tailwindcss",
  NO tailwind.config.js, NO postcss.config.js, NO autoprefixer
- UUIDs for all primary keys (HasUuids trait)
- Storage facade for all file operations (works with local AND S3)
- Vite proxy to forward /api calls to Laravel dev server
- All text content for the frontend in src/data/ (not hardcoded)
- Mobile-first responsive design

## Testing Plan
- Backend (Pest): API CRUD tests, upload validation, queue dispatch,
  processing job, auth protection
- Frontend (Vitest): hooks, keyboard navigation, upload validation
- E2E (Playwright): full user workflows across frontend + API

## Engineering Principles
The generated code MUST follow these principles. Include them as a
mandatory section in the SPEC.md and reference them in every phase:

### SOLID
- **Single Responsibility:** Each controller method does ONE thing.
  Image processing logic does NOT live in the controller — it goes in
  the ProcessPhoto job. File path generation does NOT live in the model —
  it goes in a dedicated PhotoStorageService.
- **Open/Closed:** Use Laravel's contract bindings. Image processing
  should accept any ImageProcessorInterface — current implementation
  uses Intervention Image v3, but swapping to Imagick or libvips
  shouldn't require touching the job.
- **Liskov Substitution:** Storage drivers (local/S3) are interchangeable
  through Storage facade — never use direct file paths.
- **Interface Segregation:** Don't create god-interfaces. Photo doesn't
  need to implement Searchable, Taggable, Uploadable, Favoritable as
  one interface — split them or use Eloquent traits.
- **Dependency Inversion:** Inject dependencies via constructor.
  PhotoController receives PhotoRepository, not raw queries.
  ProcessPhoto job receives ImageProcessor service, not new ImageManager().

### REST
- **Resources, not actions:** /photos (not /getPhotos or /create-photo)
- **HTTP verbs map to operations:** GET (read), POST (create),
  PUT (full update), PATCH (partial update), DELETE (remove)
- **Status codes:** 200 (OK), 201 (Created), 204 (No Content),
  400 (Bad Request), 401 (Unauthorized), 403 (Forbidden),
  404 (Not Found), 409 (Conflict — duplicate album name),
  422 (Validation Error), 429 (Rate Limit), 500 (Server Error)
- **Stateless:** Every request contains all info needed
  (Sanctum token in Authorization header)
- **HATEOAS-light:** Include relevant links in responses
  (photo.album_url, photo.tags as nested resources)
- **Idempotency:** PUT and DELETE must be idempotent.
  POST /photos creates a new resource. PATCH /photos/{id}/favorite
  is intentionally not idempotent (toggle).
- **Versioning:** /api/v1/ prefix from day one
- **Pagination:** Cursor or page-based, with metadata
  (current_page, total, per_page, last_page, links)
- **Filtering via query params:** /photos?tag=nature&album_id=xxx
  (NOT in path: /photos/by-tag/nature)
- **Consistent response envelope:**
  Success: { "data": {...}, "meta": {...} }
  Error: { "message": "...", "errors": {...} }

### KISS (Keep It Simple)
- **No premature abstraction:** Don't create a Repository pattern
  if you only have one Eloquent model usage. Use the model directly.
- **No premature optimization:** Don't add Redis caching before
  measuring if the database is slow.
- **Standard Laravel patterns:** Use Eloquent, Form Requests,
  API Resources, Policies — NOT custom middleware stacks for
  things Laravel already provides.
- **Readable over clever:** A 10-line method that's obvious beats
  a 3-line method using closures within closures.
- **Convention over configuration:** Follow Laravel naming
  (PhotoController, PhotosTable, PhotoFactory). Don't invent
  new conventions.
- **One concept per file:** Don't mix the Photo model with its
  Observer in the same file.

### DRY (Don't Repeat Yourself)
- **Extract Form Requests for validation rules** (StorePhotoRequest)
- **Extract API Resources for response formatting** (PhotoResource)
- **Use Eloquent scopes for repeated query patterns**
  (Photo::favorites(), Photo::byTag('nature'))
- **Frontend: extract custom hooks** (usePhotos, useAlbums)
- **Frontend: ALL text content in src/data/content.js** (no hardcoded strings)

### Other Standards
- **PSR-12** for PHP code style (enforced by Laravel Pint)
- **Type declarations** on all PHP method parameters and return types
- **No N+1 queries** — always use eager loading: Photo::with('album', 'tags')
- **Database transactions** for multi-step operations
- **Authorization via Policies** (PhotoPolicy, AlbumPolicy)
- **Validation via Form Requests** (never inline $request->validate())
- **No business logic in controllers** — extract to services or jobs
- **No business logic in Eloquent models** — keep models focused on
  data + relationships. Business logic goes in Action classes,
  Services, or Jobs.

## What I need you to generate
Generate FOUR files (this is the modern spec-driven pattern — separating
WHAT, HOW, and EXECUTION makes parallel agent work much more effective):

### 1. CLAUDE.md — Project Constitution
Tech stack with versions, engineering principles (SOLID, REST, KISS, DRY)
with concrete examples for THIS project, parallelization rules, code
conventions. Include meta-rule: "Always read docs/REQUIREMENTS.md and
docs/DESIGN.md before implementing tasks. Always update docs/TASKS.md
checkboxes after completing work."

### 2. docs/REQUIREMENTS.md — What to Build (Business Layer)
User stories, features in plain language, acceptance criteria,
constraints. Could be shown to a non-technical stakeholder.
NO technical decisions, NO database schemas, NO code.

### 3. docs/DESIGN.md — How to Build It (Engineering Layer)
- Complete tech stack table with versions
- Full folder/file architecture for backend/ and frontend/
  (including services/, actions/, policies/ directories)
- Database schema — every table with all columns, types,
  constraints, indexes, ON DELETE behavior
- Eloquent relationships
- API contracts — every endpoint with HTTP verb, URL, request body,
  response shape, status codes, query parameters (RESTful design)
- Filament resources specification
- Frontend component specifications
- Service interfaces (ImageProcessor, PhotoStorage — for DIP)
- Authentication strategy
- Error handling specification with EXACT status codes per scenario
- Image processing pipeline specification

### 4. docs/TASKS.md — Execution Plan (Agent Layer)
Atomic tasks (each ≤ 30 minutes of work). Each task has:
- **ID** (T001, T002, ...)
- **Title** and short description
- **Status** checkbox `[ ]`
- **Owner** — which agent (backend-dev, frontend-dev, code-reviewer)
- **Depends on** — which task IDs must finish first
- **Parallel with** — which task IDs can run simultaneously
- **Reads** — which sections of REQUIREMENTS/DESIGN to consult
- **Acceptance criteria** — how to verify the task is done

Group tasks into 13 phases (one per build workflow phase).
Total project: 80-120 atomic tasks.

CRITICAL: For each task, identify parallelization opportunities.
Tasks in backend/ and frontend/ are ALWAYS parallel.
Independent resources (PhotoResource, AlbumResource, TagResource)
are parallel. Independent components (Navbar, Sidebar, GalleryGrid,
PhotoModal) are parallel. Backend tests and frontend tests are parallel.

Sequential dependencies that MUST be respected:
- Migrations → Models → Seeders
- Models → Controllers, Resources, Form Requests, Policies
- API → Frontend API service layer
- Components → App.jsx wiring