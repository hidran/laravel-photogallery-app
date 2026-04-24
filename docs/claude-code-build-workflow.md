Add a section called "Claude Code Build Workflow" to the spec.
List the exact order to build, phase by phase:

Phase 1: Backend foundation (models, migrations, seed)
Phase 2: API layer (controllers, routes, resources, requests)
Phase 3: Filament admin panel (resources, widgets, dashboard)
Phase 4: React frontend setup (scaffold, Tailwind v4, API service)
Phase 5: React components (grid, lightbox, upload, sidebar, keyboard)
Phase 6: Image processing pipeline (job, queue, batch)
Phase 7: Filament queue monitoring (widgets, reprocess actions)
Phase 8: AWS migration (S3 + SQS via env vars)
Phase 9: Hooks (auto-format, auto-test, safety)
Phase 10: Testing (Pest + Vitest + Playwright)
Phase 11: Git + PR (GitHub MCP)
Phase 12: Multi-agent parallel development
Phase 13: Final spec check + deploy

For each phase, write the exact prompt to give Claude Code.