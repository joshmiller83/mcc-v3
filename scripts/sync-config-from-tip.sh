#!/usr/bin/env bash
#
# Pull a live DB from a Pantheon mcc2026 environment and re-export config from it,
# so main's config/sync/ reflects that environment's real active configuration.
#
# Run this before cutting any feature/fix branch. See the "Config sync" section of
# AGENTS.md for the policy this implements.
#
# Usage: scripts/sync-config-from-tip.sh [env]
#   env defaults to "dev" (today's tip; becomes test/live as the project is promoted).

set -euo pipefail

ENV="${1:-dev}"
SITE="mcc2026"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "error: working tree is not clean. Commit, stash, or discard changes first." >&2
  exit 1
fi

if [[ "$(git rev-parse --abbrev-ref HEAD)" != "main" ]]; then
  echo "error: must be run from main (currently on $(git rev-parse --abbrev-ref HEAD))." >&2
  exit 1
fi

git fetch origin main
if [[ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]]; then
  echo "error: local main is not up to date with origin/main. Pull first." >&2
  exit 1
fi

BRANCH="chore/config-sync-${ENV}-$(date +%Y%m%d)"
echo "==> Branching ${BRANCH} off main"
git switch -c "$BRANCH"

echo "==> Pulling a live DB from ${SITE}.${ENV} (files skipped, config/content only)"
ddev pull pantheon --environment="DDEV_PANTHEON_SITE=${SITE},DDEV_PANTHEON_ENVIRONMENT=${ENV}" --skip-files -y

echo "==> Exporting active config"
ddev drush config:export -y

git add config/

if git diff --cached --quiet; then
  echo "==> No config drift found; ${SITE}.${ENV} already matches main. Cleaning up."
  git switch main
  git branch -D "$BRANCH"
  exit 0
fi

echo "==> Drift found:"
git diff --cached --stat

git commit -m "config: sync export from ${SITE}.${ENV}"
git push -u origin "$BRANCH"

echo
echo "==> Pushed. Review the diff, then open a PR:"
echo "    gh pr create --title \"config: sync export from ${SITE}.${ENV}\" --body \"Reconciles main's config/sync with ${SITE}.${ENV}'s active config before branching further work.\""
