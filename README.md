# PhotoGallery Pro

A full-stack photo gallery application. Users upload photos (JPG/PNG/WebP, up to 10 MB each, 20 at a time), organize them into albums, tag them, and browse via a masonry-grid React SPA with lightbox and keyboard navigation.

## Architecture

- **Backend:** Laravel 13 REST API (`/api/v1`) + Filament v5 admin panel (`/admin`)
- **Frontend:** React 19 + Vite 6 + Tailwind CSS v4 SPA
- **Image processing:** Queued background jobs (resize + EXIF extraction)
- **Auth:** Sanctum token-based (24h TTL)

## Documentation

| File | Purpose |
|---|---|
| [CLAUDE.md](CLAUDE.md) | How we work — rules, principles, style |
| [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md) | What we're building |
| [docs/DESIGN.md](docs/DESIGN.md) | Technical blueprint — schemas, contracts, file paths |
| [docs/TASKS.md](docs/TASKS.md) | Atomic tasks across 13 phases |
| [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) | Contribution guidelines |

## Quickstart

### Prerequisites

- PHP 8.3+
- Node.js 20+
- Composer
- npm

### Setup

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Frontend
cd ../frontend
npm install
```

### Run

```bash
# Start all services (recommended)
cd backend && composer run dev

# Or run individually:
cd backend && php artisan serve          # API at :8000
cd backend && php artisan queue:listen   # Queue worker
cd frontend && npm run dev               # Vite at :5173
```

### Test

```bash
# Backend
cd backend && php artisan test --compact

# Frontend
cd frontend && npm test -- --run
```

### Code style

```bash
# PHP
cd backend && vendor/bin/pint --dirty

# TypeScript/CSS
cd frontend && npx prettier --write .
cd frontend && npx eslint .
```

## Environment

Same code runs locally and in production — only env vars change:

| Local | Production |
|---|---|
| SQLite/MySQL | MySQL/Postgres |
| Database queue | AWS SQS |
| Local disk | AWS S3 (two buckets) |
