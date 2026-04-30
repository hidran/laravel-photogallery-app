# Contributing to PhotoGallery Pro

## Getting started

1. Clone the repo
2. Install backend deps: `cd backend && composer install`
3. Install frontend deps: `cd frontend && npm install`
4. Copy `.env.example` to `.env` and configure
5. Run migrations: `php artisan migrate --seed`
6. Start dev: `composer run dev`

## Workflow

1. Pick a task from `docs/TASKS.md` whose dependencies are met
2. Read the sections listed in the task's `Reads` field
3. Create a branch: `feat/<phase>-<task-description>`
4. Implement the task
5. Run tests before committing
6. Mark the task `[x]` in TASKS.md
7. Open a PR using the template

## Commit conventions

Use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat(scope): description` — new feature
- `fix(scope): description` — bug fix
- `test(scope): description` — tests only
- `chore(scope): description` — tooling, config
- `docs(scope): description` — documentation
- `refactor(scope): description` — code restructuring

## Code style

- **Backend:** Laravel Pint with Laravel preset. Run `vendor/bin/pint --dirty`
- **Frontend:** Prettier + ESLint. Run `npx prettier --write .` and `npx eslint .`

## Testing

- **Backend:** Pest. Run `php artisan test --compact`
- **Frontend:** Vitest. Run `npm test -- --run`
- Every change must have tests. No `--no-verify`, no `.skip`

## Architecture

Read `docs/DESIGN.md` before implementing. It's authoritative for schemas, endpoints, and file paths.
