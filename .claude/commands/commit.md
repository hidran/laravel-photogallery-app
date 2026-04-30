Generate a Conventional Commits message for the current staged changes.

1. Run `git diff --cached --stat` to see what files changed
2. Run `git diff --cached` to see the actual changes
3. Determine the commit type:
   - `feat` — new feature or endpoint
   - `fix` — bug fix
   - `test` — tests only
   - `chore` — tooling, config, deps
   - `docs` — documentation
   - `refactor` — restructuring without behavior change
   - `perf` — performance improvement
4. Determine the scope from the changed files:
   - `api` — controllers, routes, requests, resources
   - `backend` — models, services, actions, queries
   - `frontend` — React components, hooks, pages
   - `filament` — admin panel resources, widgets
   - `pipeline` — jobs, queue, image processing
   - `db` — migrations, seeders
   - No scope for cross-cutting changes
5. Write a concise subject line (imperative mood, no period, under 72 chars)
6. Reference DESIGN.md sections in the body if applicable
7. Create the commit with this format

Do NOT push. Just create the commit.
