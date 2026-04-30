#!/usr/bin/env bash
# Creates a git worktree for parallel development.
# Usage: ./scripts/worktree-new.sh <branch-name>
# Creates ../pgp-<branch-name> worktree

set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: $0 <branch-name>"
  echo "Example: $0 feat/api-stats"
  exit 1
fi

BRANCH="$1"
WORKTREE_DIR="../pgp-${BRANCH//\//-}"

if [ -d "$WORKTREE_DIR" ]; then
  echo "Error: Worktree directory $WORKTREE_DIR already exists"
  exit 1
fi

# Create branch if it doesn't exist
if ! git show-ref --verify --quiet "refs/heads/$BRANCH" 2>/dev/null; then
  git branch "$BRANCH"
fi

git worktree add "$WORKTREE_DIR" "$BRANCH"
echo "Created worktree at $WORKTREE_DIR on branch $BRANCH"
echo ""
echo "To start working:"
echo "  cd $WORKTREE_DIR"
echo ""
echo "To remove when done:"
echo "  git worktree remove $WORKTREE_DIR"
