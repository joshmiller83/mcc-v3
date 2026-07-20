# AGENTS.md

Rules for AI coding assistants (Claude Code, Codex, Copilot, etc.) working in this repo. If you are an AI CLI tool operating here, read this before making changes.

## What this project is

A rebuild of the Mechanicsburg Christian Church website (mechanicsburgchristian.com) on a fresh Drupal CMS install, hosted on Pantheon. We are a small country church — the site should stay simple, clear, and easy for non-specialists to maintain going forward. Don't over-engineer.

## Ground rules

- **Clarity over cleverness.** Prefer the obvious Drupal-native way of doing something over a custom or clever solution. If you find yourself writing custom code to solve something core or a well-supported contrib module already solves, stop and use that instead.
- **Straightforward changes.** Small, understandable commits. Explain *why* in commit messages when the reason isn't obvious from the diff.
- **Stay upgradable.** This site should be easy to keep current with future Drupal CMS releases. Avoid patterns that fight core defaults or make future updates harder.
- **No unnecessary scope.** Don't refactor, redesign, or "improve" things beyond what was asked.

## Environment: GitHub Codespaces only

This project is developed in **GitHub Codespaces**, not on a local machine. DDEV runs inside the Codespace.

- **If you are running inside the Codespace environment:** you have full access to run `ddev drush`, `ddev composer`, and `ddev terminus` directly to add modules, run updates, configure the site, and manage it hands-on. Go ahead and use these tools to get work done.
- **If you are NOT running inside the Codespace environment** (e.g. operating on this repo from a local checkout or another context): limit yourself to editing **code and configuration files**. Do not attempt interactive work — no starting `ddev`, no running `drush`/`composer`/`terminus` commands, no assuming a running site or database exists. Make your changes as file edits, commit them, and let Codespaces/CI pick them up.

`.devcontainer/devcontainer.json` is the source of truth for how the Codespace is provisioned: a base Debian image with the `docker-in-docker` and DDEV's official `install-ddev` devcontainer features layered on. That feature setup was confirmed directly against the `ddev/ddev` source (not just docs) — if you change it, re-check `containers/devcontainers/install-ddev/` in that repo rather than assuming the pattern is still current.

Codespaces prebuilds are **not** configured. That's a repo Settings → Codespaces UI action, not something expressible in `devcontainer.json` or via `gh` — it's a manual, opt-in step (it consumes Codespaces storage quota) left to a human to decide on.

## Workflow

- Small, incremental commits — don't batch unrelated changes together.
- Push after each commit rather than letting work pile up unpushed.
- Work directly on `main` for now. This is early-stage and low-complexity enough that feature branches would just add overhead; revisit this once multiple people or longer-running changes are involved.
- Keep this file and `README.md` up to date whenever an architectural decision is made (new tooling, new environment setup, etc.) — update them as part of finishing the work, not as an afterthought.

## Tooling reference

- `ddev drush <command>` — Drush, for site administration, config import/export, cache rebuilds, etc.
- `ddev composer <command>` — Composer, for adding/updating modules, themes, and dependencies.
- `ddev terminus <command>` — Terminus, giving access to the Pantheon `dev`, `test`, and `live` environments of this site. Treat `test` and `live` with care — these are real environments, not scratch space. Prefer read-only Terminus commands (checking status, logs, backups) unless a change to those environments has been explicitly requested.

## Deploys

Pushing to GitHub triggers the Pantheon build process automatically. There's no separate manual deploy step to remember — just make sure what you push is something you'd want built and deployed.
