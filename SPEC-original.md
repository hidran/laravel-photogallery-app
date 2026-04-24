# PhotoGallery Pro — Technical Specification (Full-Stack)

## 1. Project Overview

**Name:** PhotoGallery Pro
**Type:** Full-stack web application
**Architecture:** Laravel API backend + React SPA frontend + Filament admin panel
**Purpose:** A photo gallery with albums, tagging, filtering, masonry layout, lightbox,
and a full admin panel for managing photos, albums, users, and settings.

## 2. Tech Stack

| Layer | Technology | Version | Notes |
|-------|-----------|---------|-------|
| **Backend** | Laravel | 13.x | Released March 17, 2026. Requires PHP 8.3+. Includes AI SDK (stable). |
| **Admin Panel** | Filament | 5.x | Requires Livewire v4, TALL stack |
| **Frontend** | React | 19.x | Vite 6, SPA consuming API |
| **Styling** | Tailwind CSS | 4.x | @tailwindcss/vite plugin (NO config file) |
| **Database** | SQLite (dev) / PostgreSQL (prod) | — | Laravel migrations |
| **Auth** | Laravel Sanctum | — | API token auth for React, session auth for Filament |
| **Storage** | Laravel Storage | — | Local disk (dev), S3 (prod) |
| **Icons** | Lucide React | 0.577+ | Frontend only |
| **Testing** | Pest (backend) + Vitest (frontend) + Playwright (E2E) | — | — |

## 3. Architecture

```
photogallery-pro/
├── SPEC.md
├── CLAUDE.md
│
├── backend/                        ← Laravel 13 application (PHP 8.3+)
│   ├── app/
│   │   ├── Models/
│   │   │   ├── Photo.php           # Eloquent model
│   │   │   ├── Album.php           # Eloquent model
│   │   │   └── Tag.php             # Eloquent model
│   │   ├── Http/
│   │   │   └── Controllers/Api/
│   │   │       ├── PhotoController.php
│   │   │       ├── AlbumController.php
│   │   │       └── TagController.php
│   │   ├── Filament/
│   │   │   ├── Resources/
│   │   │   │   ├── PhotoResource.php      # CRUD for photos
│   │   │   │   ├── AlbumResource.php      # CRUD for albums
│   │   │   │   └── TagResource.php        # CRUD for tags
│   │   │   ├── Widgets/
│   │   │   │   ├── StatsOverview.php      # Dashboard stats
│   │   │   │   └── RecentUploads.php      # Recent uploads table
│   │   │   └── Pages/
│   │   │       └── Dashboard.php
│   │   └── Policies/
│   │       ├── PhotoPolicy.php
│   │       └── AlbumPolicy.php
│   ├── database/
│   │   ├── migrations/
│   │   │   ├── create_photos_table.php
│   │   │   ├── create_albums_table.php
│   │   │   ├── create_tags_table.php
│   │   │   └── create_photo_tag_table.php   # pivot
│   │   ├── seeders/
│   │   │   └── DatabaseSeeder.php           # sample data
│   │   └── factories/
│   │       ├── PhotoFactory.php
│   │       └── AlbumFactory.php
│   ├── routes/
│   │   └── api.php                          # API routes
│   ├── tests/
│   │   ├── Feature/
│   │   │   ├── PhotoApiTest.php
│   │   │   └── AlbumApiTest.php
│   │   └── Unit/
│   │       └── PhotoTest.php
│   └── storage/app/public/photos/           # uploaded files
│
├── frontend/                       ← React 19 SPA
│   ├── src/
│   │   ├── components/
│   │   │   ├── GalleryGrid.jsx
│   │   │   ├── PhotoCard.jsx
│   │   │   ├── PhotoModal.jsx
│   │   │   ├── UploadPanel.jsx
│   │   │   ├── Sidebar.jsx
│   │   │   └── Navbar.jsx
│   │   ├── hooks/
│   │   │   ├── usePhotos.js         # API client + state
│   │   │   ├── useAlbums.js         # API client + state
│   │   │   └── useKeyboard.js       # keyboard navigation
│   │   ├── services/
│   │   │   └── api.js               # Axios/fetch wrapper for Laravel API
│   │   ├── data/
│   │   │   └── samplePhotos.js      # Fallback demo data
│   │   └── App.jsx
│   ├── e2e/                         # Playwright E2E tests
│   ├── vite.config.js
│   └── package.json
│
└── .claude/                        ← Claude Code config
    ├── settings.json               # Permissions + hooks
    ├── agents/
    │   ├── backend-dev.md          # Laravel specialist
    │   ├── frontend-dev.md         # React specialist
    │   └── code-reviewer.md        # Spec auditor
    └── skills/
        ├── commit/SKILL.md
        ├── spec-check/SKILL.md
        └── deploy/SKILL.md
```

## 4. Data Models

### 4.1 Database Schema (Laravel Migrations)

**photos**
| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | primary key, default uuid() |
| title | string(255) | not null |
| description | text | nullable |
| filename | string(255) | not null |
| path | string(255) | not null (storage path) |
| thumbnail_path | string(255) | nullable |
| album_id | uuid | nullable, foreign key → albums.id, on delete set null |
| width | integer | nullable |
| height | integer | nullable |
| file_size | integer | nullable (bytes) |
| mime_type | string(50) | not null |
| is_favorite | boolean | default false |
| created_at | timestamp | auto |
| updated_at | timestamp | auto |

**albums**
| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | primary key |
| name | string(255) | not null, unique |
| description | text | nullable |
| cover_photo_id | uuid | nullable, foreign key → photos.id, on delete set null |
| created_at | timestamp | auto |
| updated_at | timestamp | auto |

**tags**
| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | primary key |
| name | string(100) | not null, unique |
| slug | string(100) | not null, unique |

**photo_tag** (pivot)
| Column | Type | Constraints |
|--------|------|-------------|
| photo_id | uuid | foreign key → photos.id, on delete cascade |
| tag_id | uuid | foreign key → tags.id, on delete cascade |
| Primary key: (photo_id, tag_id) |

### 4.2 Eloquent Relationships
- Photo belongsTo Album
- Photo belongsToMany Tags (via photo_tag pivot)
- Album hasMany Photos
- Album belongsTo Photo (cover_photo)
- Tag belongsToMany Photos

## 5. API Endpoints (Laravel)

Base URL: `/api/v1`

### Photos
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /photos | List photos (paginated, filterable, sortable) |
| GET | /photos/{id} | Get single photo with tags and album |
| POST | /photos | Upload photo(s) — multipart/form-data |
| PUT | /photos/{id} | Update photo metadata |
| PATCH | /photos/{id}/favorite | Toggle favorite |
| DELETE | /photos/{id} | Delete photo (with file cleanup) |

**GET /photos query params:**
- `search` — filter by title (LIKE) and tag names
- `tag` — filter by tag slug(s), comma-separated (AND logic)
- `album_id` — filter by album
- `is_favorite` — boolean filter
- `sort` — newest (default), oldest, title_asc, title_desc, favorites
- `per_page` — default 24, max 100

### Albums
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /albums | List albums with photo counts |
| GET | /albums/{id} | Get album with photos |
| POST | /albums | Create album |
| PUT | /albums/{id} | Update album (name, description, cover) |
| DELETE | /albums/{id} | Delete album (photos become unassigned) |

### Tags
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /tags | List all tags with photo counts |

## 6. Filament Admin Panel

### 6.1 Resources

**PhotoResource**
- Table: thumbnail preview, title, album name, tags (badges), file size, is_favorite (toggle), created_at
- Filters: album, tag, favorite, date range
- Bulk actions: delete, assign to album, toggle favorite
- Form: file upload (drag-drop), title, description, album select, tags multi-select (with create), is_favorite toggle
- View: full image preview, metadata infolist, related album

**AlbumResource**
- Table: name, photo count (computed), cover photo thumbnail, created_at
- Form: name (unique validation), description, cover photo select
- Relation Manager: photos belonging to this album

**TagResource**
- Table: name, slug (auto-generated), photo count
- Form: name (auto-generates slug)

### 6.2 Dashboard Widgets
- **StatsOverview**: total photos, total albums, total tags, storage used
- **RecentUploads**: table of last 10 uploaded photos with thumbnails
- **PhotosByAlbum**: pie/doughnut chart of photos per album

### 6.3 Custom Admin Features
- Bulk upload page: upload multiple photos at once
- Storage management: show disk usage, thumbnail regeneration
- Export: CSV export of photo metadata

## 7. Frontend Features (React SPA)

*(Same as the original spec sections 4.1-4.6: Gallery Grid, Lightbox,
Upload, Sidebar with albums/tags, Search/Filter, Keyboard Shortcuts)*

### API Integration
- src/services/api.js wraps all API calls using fetch or axios
- Handles Sanctum token authentication
- Retry logic for failed requests
- Optimistic updates for favorites toggle
- File upload with progress tracking via XMLHttpRequest

## 8. Authentication

- **Filament admin**: Laravel session auth (email/password via Filament login)
- **React frontend**: Laravel Sanctum API tokens
  - POST /api/auth/login → returns token
  - POST /api/auth/register → creates user + returns token
  - Token stored in localStorage, sent as Bearer header
  - Public gallery view (no auth required for browsing)
  - Auth required for: upload, edit, delete, manage albums

## 9. Error Handling

### API Errors (Laravel)
- 422: Validation errors (JSON: `{ errors: { field: [messages] } }`)
- 404: Photo/Album not found
- 413: File too large (max 10MB)
- 415: Unsupported media type (only jpg, png, webp)
- 500: Server error (logged, generic message to client)

### Frontend Errors (React)
- Toast notifications for API errors
- Inline validation for forms
- Empty states with CTA for empty gallery/album/search results
- Network error retry with exponential backoff

## 10. Testing Plan

### Backend (Pest)
- [ ] Photo CRUD API: create, read, update, delete
- [ ] Photo upload: valid files, invalid types, size limits
- [ ] Photo filtering: search, tag, album, favorites, sort
- [ ] Album CRUD: create, rename, delete (photos unassigned)
- [ ] Tag management: auto-create on photo update
- [ ] Auth: login, register, protected routes, public routes
- [ ] File cleanup: deleting photo removes files from storage

### Frontend (Vitest)
- [ ] usePhotos hook: fetch, filter, sort, optimistic updates
- [ ] useAlbums hook: fetch, create, delete
- [ ] PhotoModal: keyboard nav, edit metadata, delete
- [ ] UploadPanel: file validation, progress tracking

### E2E (Playwright)
- [ ] Full upload → view → edit → delete workflow
- [ ] Album creation and photo assignment
- [ ] Search and filter functionality
- [ ] Keyboard shortcuts in lightbox
- [ ] Responsive layout at 375px, 768px, 1024px, 1440px

## 11. Claude Code Workflow

```bash
# Phase 1: Backend Foundation
claude "Read SPEC.md. Create the Laravel 13 project in backend/ with:
  - laravel new backend (no starter kit, SQLite)
  - Requires PHP 8.3+
  - Install Sanctum for API auth
  - Create all migrations from section 4.1
  - Create Eloquent models with relationships from section 4.2
  - Use PHP attributes where appropriate (Laravel 13 feature)
  - Create model factories and seeders with 20 sample photos
  - Run migrations and seed"

# Phase 2: API Layer
claude "Read SPEC.md section 5. Build all API controllers and routes:
  - PhotoController with all endpoints
  - AlbumController with all endpoints
  - TagController
  - Form requests for validation
  - API resources for JSON formatting
  - Register routes in api.php"

# Phase 3: Filament Admin
claude "Read SPEC.md section 6. Install Filament v5 and build:
  - composer require filament/filament:'^5.0'
  - php artisan filament:install --panels
  - PhotoResource with table, form, filters, bulk actions
  - AlbumResource with relation manager for photos
  - TagResource
  - Dashboard widgets: StatsOverview, RecentUploads
  - Create admin user in seeder"

# Phase 4: Frontend Setup
claude "Read SPEC.md. Create the React frontend in frontend/:
  - npm create vite@latest frontend -- --template react
  - Install Tailwind CSS v4: npm install tailwindcss @tailwindcss/vite
  - Configure @tailwindcss/vite plugin (NO tailwind.config.js)
  - @import 'tailwindcss' in index.css
  - Install lucide-react, axios
  - Create src/services/api.js with Sanctum auth and all API calls"

# Phase 5: Frontend Components
claude "Read SPEC.md section 7. Build all React components:
  - GalleryGrid, PhotoCard, PhotoModal, UploadPanel, Sidebar, Navbar
  - Hooks: usePhotos, useAlbums, useKeyboard
  - Wire into App.jsx with routing"

# Phase 6: Testing
claude "Read SPEC.md section 10. Write all tests:
  - Backend: Pest tests for API endpoints
  - Frontend: Vitest tests for hooks and components
  - E2E: Playwright tests for full workflows"

# Phase 7: Review
claude "Run /spec-check to verify everything against SPEC.md"
```
