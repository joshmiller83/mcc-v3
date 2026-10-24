# Agent guidance for this Drupal site

Rules for AI coding assistants (Claude Code, Codex, Copilot, Antigravity, etc.) working in this repo. If you are an AI CLI tool operating here, read this before making changes.

## What this project is

A rebuild of the Mechanicsburg Christian Church website (mechanicsburgchristian.com) on a fresh Drupal CMS install, hosted on Pantheon. We are a small country church — the site should stay simple, clear, and easy for non-specialists to maintain going forward. Don't over-engineer.

## Ground rules

- **Clarity over cleverness.** Prefer the obvious Drupal-native way of doing something over a custom or clever solution. If you find yourself writing custom code to solve something core or a well-supported contrib module already solves, stop and use that instead.
- **Straightforward changes.** Small, understandable commits. Explain *why* in commit messages when the reason isn't obvious from the diff.
- **Stay upgradable.** This site should be easy to keep current with future Drupal CMS releases. Avoid patterns that fight core defaults or make future updates harder.
- **No unnecessary scope.** Don't refactor, redesign, or "improve" things beyond what was asked.

## Where this runs

This project is developed in **GitHub Codespaces**, not on a local machine. DDEV runs inside the Codespace.

- **If you are running inside the Codespace environment:** you have full access to run `ddev drush`, `ddev composer`, and `ddev terminus` directly to add modules, run updates, configure the site, and manage it hands-on. Go ahead and use these tools to get work done.
- **If you are NOT running inside the Codespace environment** (e.g. operating on this repo from a local checkout or another context): limit yourself to editing **code and configuration files**. Do not attempt interactive work — no starting `ddev`, no running `drush`/`composer`/`terminus` commands, no assuming a running site or database exists. Make your changes as file edits, commit them, and let Codespaces/CI pick them up.

## Guardrails

- Do not commit secrets or machine-local overrides such as `.env`, `settings.local.php`, or `.ddev/config.local.yaml`.
- Do not commit `vendor/` or uploaded files under `web/sites/*/files`.
- Do not edit Drupal core or contributed projects in place.
- Put custom code in `web/modules/custom` and `web/themes/custom`.
- Prefix custom modules, custom themes, and Single Directory Components (SDC) with `mcc_` (or `mcc-` for component/folder names) where possible.

## Workflow

- Small, incremental commits — don't batch unrelated changes together. On a long task, commit
  and push each step as it lands rather than once at the end; every push is also a backup.
- **Push every commit to `main` on GitHub.** Don't let work sit committed-but-unpushed — a push
  to `main` is what triggers the deploy pipeline (see [deploys.md](docs/agents/deploys.md)),
  so unpushed work is invisible to it. Work directly on `main` for now. This is early-stage
  and low-complexity enough that feature branches would just add overhead; revisit this once
  multiple people or longer-running changes are involved. The one exception is config-drift
  capture (see [config-sync.md](docs/agents/config-sync.md)) — that goes through a short-lived
  PR, since it's reconciling `main` with whatever the tip environment's active config actually
  is, not a reviewed code change, and deserves a look before merging.
- Keep these docs (see [Keeping these docs](#keeping-these-docs)) and `README.md` up to date
  whenever an architectural decision is made or a session learns something non-obvious —
  as part of finishing the work, not as an afterthought.

## Secrets & tokens

Before asking the user to log in to a CLI interactively, check whether a token is already provisioned as a Codespaces secret:

- Run `compgen -e | grep -iE "token|key|secret"` on the Codespace host to see what's already available (e.g. `PANTHEON_MACHINE_TOKEN`, `GITHUB_TOKEN`). **`compgen -e` prints names only — do not use `env | grep` for this.** This line used to say `env | grep`, which prints the values too, and piping it through `cut -d= -f1` does not save you: `GDRIVE_SA_KEY` is multi-line JSON, so every continuation line — the service account's private key included — has no `=` to cut on and is printed whole. That is how the key ended up in a session transcript. Codespaces secrets land as host-level environment variables, not inside the ddev containers, so they need to be passed through explicitly — over stdin, as below, never as an argument.
- **`.ddev/config.local.yaml` holds a literal `TERMINUS_MACHINE_TOKEN`** (it is what `ddev pull pantheon`'s auth step reads inside the web container). It is gitignored, but `cat`-ing or grepping it puts the token in the transcript. If you need to know whether the key is set, `grep -c TERMINUS_MACHINE_TOKEN` it.
- Never echo a token's value into a command you type out or into chat — reference it via its env var name so the literal value never appears in the transcript.
- **`ddev exec` reconstructs and prints the full expanded command line (including argv) when the command fails.** Passing a secret as a command-line argument — even referenced via `$VAR` — risks that value being echoed verbatim into the error output the moment anything goes wrong (seen firsthand: a failed `ddev exec bash -c '...' _ "$TOKEN"` printed the literal token in the tool output). Pipe secrets via stdin instead: `ddev exec bash -c 'terminus auth:login --machine-token="$(cat)"' <<< "$TOKEN"`.
- Only fall back to asking the user for a token/login if nothing suitable turns up in the environment.
- The Codespace's ambient `GITHUB_TOKEN` (what `gh` auths with by default) can push code and open PRs but returns 403 on `gh secret set` / `gh secret list` — managing a repo's Actions secrets needs a user-supplied fine-grained PAT scoped to that repo with "Secrets: Read and write".

## Topics

The standing rules for each area live in `docs/agents/`, one file per area, each rule with the
failure that taught it. They are not loaded automatically: read the file for an area before
changing anything in it.

| Read before you touch…                                                        | File                                                          |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------- |
| colours, type, the logo, the favicon                                          | [design.md](docs/agents/design.md)                            |
| `/calendar`, `/calendar/print`, `EventContext`, `CalendarMonth`, event pages  | [calendar.md](docs/agents/calendar.md)                        |
| the nav, the footer menus, Canvas page bands, aliases, redirects              | [ia-menus-urls.md](docs/agents/ia-menus-urls.md)              |
| a `scripts/*.php` that writes content, or getting content to another env     | [content-scripts.md](docs/agents/content-scripts.md)          |
| `/about/leadership` and bio pages                                             | [leadership.md](docs/agents/leadership.md)                    |
| `/ministries` and ministry pages                                              | [ministries.md](docs/agents/ministries.md)                    |
| the front page's announcements and the announcement page                      | [announcements.md](docs/agents/announcements.md)              |
| any `migrate:import`, the `legacy` database, a D7 sync                        | [d7-migration.md](docs/agents/d7-migration.md)                |
| an icon anywhere                                                              | [icons.md](docs/agents/icons.md)                              |
| the Codespace, ddev, Google Drive, pulling both sites                         | [environment.md](docs/agents/environment.md)                  |
| `composer update`, update hooks, patches, the PHP version                     | [dependency-updates.md](docs/agents/dependency-updates.md)    |
| Terminus, drush on Pantheon, SSH, the Playwright scripts                      | [tooling.md](docs/agents/tooling.md)                          |
| a push to `main`, Pantheon builds, the Quicksilver hook                       | [deploys.md](docs/agents/deploys.md)                          |
| config drift between the tip and `main`                                       | [config-sync.md](docs/agents/config-sync.md)                  |

`CONTENT_LOG.md` records every content and structure change made outside code, and is the
first thing to read before re-running a migration.

## Open items

- **The tip (mcc2026.dev) has not had the 2026-10-01 or 2026-10-24 syncs.** Its web tier has
  answered `504 Target in maintenance` since about 2026-09-08; code still deploys to it, and it
  runs PHP 8.5.12. One dump carries both syncs — the steps are under "Tip hand-off" in
  [the 2026-10-24 update](docs/agents/updates/2026-10-24-calendar-sync-php-85-docs-split.md#tip-hand-off).
- **Calendar choices left to the church** are listed in that day's `CONTENT_LOG.md` entry.

## Updates

Newest first. Each is what one session changed and learned, with links to the rules it added.
Read the latest one or two at the start of a session; older ones only when their topic comes up.

- [2026-10-24](docs/agents/updates/2026-10-24-calendar-sync-php-85-docs-split.md) — D7
  calendar sync (field-level conflict compare, D7 deletions); PHP 8.5.12 and that month's
  security advisories; this file split into topics.
- Earlier sessions wrote straight into the rules, which are now the topic files. Their content
  changes are in `CONTENT_LOG.md`, and `git log -- AGENTS.md` is the history.

## Keeping these docs

- **A new standing rule goes in its topic file**, in that file's voice: the rule in bold, then
  the concrete failure that taught it, with real numbers. If an existing rule turns out to be
  wrong, fix it in place rather than adding one that contradicts it.
- **What a session did and learned goes in a dated update**,
  `docs/agents/updates/YYYY-MM-DD-<slug>.md`: short, linking to the rules it added or changed,
  plus anything left open. Add one line for it under Updates, newest first, and keep Open items
  current.
- **A new area gets a new topic file and a row in Topics.** Keep this file to what every
  session needs; detail belongs in the topic files, which is what keeps it short.
- Content and structure changes made outside code still go in `CONTENT_LOG.md`.
