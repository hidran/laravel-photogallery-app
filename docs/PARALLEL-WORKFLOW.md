# Parallel Workflow with Git Worktrees

How to use git worktrees and Claude Code agents to work on multiple tasks simultaneously without merge conflicts.

---

## 1. Overview

PhotoGallery Pro has 120+ tasks across 13 phases. Many tasks within the same phase touch completely disjoint files -- different controllers, different components, different test files. Running these sequentially wastes time. Git worktrees let you check out multiple branches from the same repository simultaneously, each in its own directory, so multiple Claude Code agents can work in parallel without stepping on each other.

**Benefits:**

- No merge conflicts on disjoint file sets
- 2-5x throughput on parallelizable phases
- Each agent has a clean working directory -- no half-finished changes from another task
- All worktrees share the same `.git` object store, so branches stay in sync

---

## 2. Prerequisites

- **Git 2.15+** (worktree support). Verify: `git --version`
- The helper script at `scripts/worktree-new.sh` (already in the repo)
- Make the script executable: `chmod +x scripts/worktree-new.sh`

---

## 3. Creating a worktree

Use the helper script from the project root:

```bash
./scripts/worktree-new.sh <branch-name>
```

**What it does:**

1. Creates the branch if it does not already exist
2. Checks it out into `../pgp-<branch-name>` (slashes replaced with dashes)
3. Prints the path and cleanup instructions

**Example:**

```bash
./scripts/worktree-new.sh feat/2-tag-controller
# Creates ../pgp-feat-2-tag-controller on branch feat/2-tag-controller
```

The worktree is a full working copy. You can `cd` into it and run `composer install`, `php artisan test`, etc. independently.

**List existing worktrees:**

```bash
git worktree list
```

---

## 4. Dispatching agents

Once you have a worktree, dispatch a Claude Code subagent to work inside it using the `Agent` tool with worktree isolation:

```
Agent tool call:
  prompt: "Complete task T030 (PhotoController). Read docs/REQUIREMENTS.md F1.* and docs/DESIGN.md section 6 first. ..."
  isolation: "worktree"
```

When using `isolation: "worktree"`, the agent runs in its own git worktree with its own branch. The main agent continues working on the current task in the original working directory.

**Key points:**

- Always include full context in the agent prompt: which task, which REQUIREMENTS.md sections, which DESIGN.md sections
- The subagent commits to its own branch in the worktree
- You can dispatch multiple subagents for multiple worktrees simultaneously
- Each agent should run tests for its own changes before committing

---

## 5. Magnet files -- never edit in parallel

These files are high-conflict targets. Only one agent should edit them at a time. If your task touches a magnet file, do not parallelize it.

| File | Why |
|---|---|
| `bootstrap/app.php` | Middleware stack, exception handler -- every middleware registration lands here |
| `routes/api.php` | All API route definitions; concurrent edits cause line-level conflicts |
| `database/migrations/*` | Filenames must be chronologically ordered; parallel creates cause ordering issues |
| `app/Providers/Filament/AdminPanelProvider.php` | Single registration point for all Filament resources and plugins |
| `frontend/src/index.css` | `@theme { ... }` block -- Tailwind v4 token definitions |
| `frontend/src/App.tsx` | Route table -- every new page adds routes here |
| `frontend/src/main.tsx` | Provider tree wrapping the app |
| `vite.config.ts` | Build configuration |
| `.env.example` / `.env.production.example` | Environment variable registry |
| `composer.json` / `package.json` | Dependency manifests |
| `lefthook.yml` | Git hook configuration |
| `.claude/settings.json` | Claude Code project settings |
| `.github/workflows/*` | CI pipeline definitions |

**Rule:** If a task's file list overlaps with a magnet file, finish it and merge before starting the next task that touches the same file.

---

## 6. Safe parallelization patterns

These task groups from `docs/TASKS.md` are verified safe to run in parallel (disjoint file sets):

| Phase | Parallel tasks | What they cover |
|---|---|---|
| 1 | T004, T005, T006 | Independent enums and config |
| 1 | T008, T009 | Albums vs tags migrations |
| 2 | T023, T024, T025, T026 | Form requests + API resources |
| 2 | T030, T031, T034, T035, T036 | Distinct controllers |
| 2 | T040, T041, T042, T043, T044 | Independent test files |
| 3 | T047, T049, T051 | Distinct Filament resources |
| 4 | T055, T056, T057 | Independent frontend setup |
| 5 | T064-T069 | Disjoint component trees |
| 6 | T073, T074, T075 | Distinct service implementations |
| 7 | T081, T082, T083 | Distinct widgets/actions |
| 8 | T086, T087, T088 | Independent docs/scripts |
| 9 | T090, T091 | Backend vs frontend tooling |
| 10 | T095, T096, T097 | Independent test layers |

**How to verify a pair is safe:** Check the `Parallel with` field on each task in `docs/TASKS.md`. If two tasks list each other, they are safe. If either touches a magnet file (`magnet: true`), run it serially.

---

## 7. Merging back

After a subagent finishes its work in a worktree:

```bash
# From the main repo directory
git checkout main                           # or your integration branch
git merge feat/2-tag-controller --no-ff     # merge the worktree branch
```

**If conflicts arise:**

1. They should only happen on magnet files (if you followed the rules above)
2. Resolve manually, keeping both sets of changes
3. Run the full test suite after resolving: `php artisan test --compact`
4. Commit the merge

**Merge order matters when multiple worktrees finish around the same time.** Merge them one at a time, running tests after each merge.

For squash merges (one commit per task, as required by CLAUDE.md):

```bash
git merge --squash feat/2-tag-controller
git commit -m "feat(api): add TagController with CRUD endpoints"
```

---

## 8. Cleanup

After merging, remove the worktree:

```bash
git worktree remove ../pgp-feat-2-tag-controller
```

If the directory was already deleted manually:

```bash
git worktree prune
```

To delete the branch after merging:

```bash
git branch -d feat/2-tag-controller
```

---

## 9. Example workflow

**Scenario:** You need to complete T030 (PhotoController) and T031 (AlbumController) from Phase 2. These are listed as safe to parallelize.

**Step 1 -- Create worktrees:**

```bash
# From project root
./scripts/worktree-new.sh feat/2-photo-controller
./scripts/worktree-new.sh feat/2-album-controller
```

**Step 2 -- Dispatch two agents:**

Agent A (PhotoController):
```
Agent tool call:
  prompt: "You are working in a Laravel project. Complete task T030 from docs/TASKS.md.
    Read docs/REQUIREMENTS.md F1 (Photo management) and docs/DESIGN.md section 6.2
    (PhotoController) before writing code. Follow all rules in CLAUDE.md.
    Run tests before committing."
  isolation: "worktree"
```

Agent B (AlbumController):
```
Agent tool call:
  prompt: "You are working in a Laravel project. Complete task T031 from docs/TASKS.md.
    Read docs/REQUIREMENTS.md F2 (Album management) and docs/DESIGN.md section 6.3
    (AlbumController) before writing code. Follow all rules in CLAUDE.md.
    Run tests before committing."
  isolation: "worktree"
```

Both agents work simultaneously in separate directories on separate branches.

**Step 3 -- Merge results:**

```bash
git checkout main
git merge --squash feat/2-photo-controller
git commit -m "feat(api): PhotoController with upload, show, update, delete"

git merge --squash feat/2-album-controller
git commit -m "feat(api): AlbumController with CRUD and cover management"

php artisan test --compact   # verify everything works together
```

**Step 4 -- Clean up:**

```bash
git worktree remove ../pgp-feat-2-photo-controller
git worktree remove ../pgp-feat-2-album-controller
git branch -d feat/2-photo-controller
git branch -d feat/2-album-controller
```

**Step 5 -- Update TASKS.md:** Mark T030 and T031 as `[x]`.

---

**End of PARALLEL-WORKFLOW.md**
