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

## What I need you to generate
Create a SPEC.md file with:
1. Project overview
2. Complete tech stack table with versions
3. Full folder/file architecture for both backend/ and frontend/
4. Database schema (all tables with columns, types, constraints)
5. Eloquent relationships
6. API endpoints table (method, URL, description, query params)
7. Filament resources specification (tables, forms, filters, actions, widgets)
8. Frontend component specifications
9. Authentication details
10. Error handling specification
11. Image processing pipeline specification (sizes, queue config, env switching)
12. Testing plan checklist
13. Step-by-step Claude Code build workflow

Make it detailed enough that Claude Code can build each section by reading
the spec — no guessing required.