# Agent guidance for this Drupal site

Rules for AI coding assistants (Claude Code, Codex, Copilot, Antigravity, etc.) working in this repo. If you are an AI CLI tool operating here, read this before making changes.

## What this project is

A rebuild of the Mechanicsburg Christian Church website (mechanicsburgchristian.com) on a fresh Drupal CMS install, hosted on Pantheon. We are a small country church — the site should stay simple, clear, and easy for non-specialists to maintain going forward. Don't over-engineer.

## Ground rules

- **Clarity over cleverness.** Prefer the obvious Drupal-native way of doing something over a custom or clever solution. If you find yourself writing custom code to solve something core or a well-supported contrib module already solves, stop and use that instead.
- **Straightforward changes.** Small, understandable commits. Explain *why* in commit messages when the reason isn't obvious from the diff.
- **Stay upgradable.** This site should be easy to keep current with future Drupal CMS releases. Avoid patterns that fight core defaults or make future updates harder.
- **No unnecessary scope.** Don't refactor, redesign, or "improve" things beyond what was asked.

## Design Brief

Our site should have a clean, warm, and professional country-church aesthetic:
- **Primary Color:** Nice dark green (`#1e4d2b`).
- **Typography:**
  - **Headings & Emphasis:** `Calistoga` (a warm, friendly display serif with soft terminals).
  - **Body & default copy:** `Nunito` (a highly readable, rounded sans-serif). Leverage various weights (300 to 900) for visual hierarchy and readability.
- **Logo:** Custom logo settings (assets to be provided by the user).
- **Subtheming:** Style customizations are encapsulated in the custom `mcc_theme` subtheme, overriding base theme design tokens.

## The calendar

`/calendar` and `/calendar/print` are built from fielded entities and config — see the "The calendar" section of `README.md` for the shape of it. When working on them:

- **Never hard-code a category's colour, shape or name** in PHP, Twig or CSS. Colour comes from `field_category_color` on the *Mission category* term and is passed down as a `--evt` custom property; shape comes from `field_marker_shape` and is passed down as a `data-shape` attribute. A new category must be addable from the admin UI alone.
- **Resolve category styling through `EventContext`**, not by reading the term's fields again somewhere else. It's the single place the fallback colour/shape and the all-day and short-time rules live.
- **Build the month grid with the `mcc_core.calendar_month` service.** Both controllers use it, so the screen and print views can't drift apart; multi-day bands are derived there from consecutive days rather than stored on the node. The front page's "This week at MCC" panel goes through the same service (`CalendarMonth::week()`) and renders the calendar's own `mcc-calendar-week` component — don't give it a second way to find or lay out events. A Views display can't produce that shape (positioned day columns, derived bands, lane packing), which is why the panel is a block plugin over the service rather than a view.
- **Re-run `node scripts/calendar-compare.mjs` before committing.** The print sheet fitting on one page is a hard requirement, and it's easy to break from a distance (a base-theme `p { font-size }` rule was enough to do it once).

## Information architecture, menus and URLs

The site's IA is five flat nav items plus a Get Involved CTA: **I'm New · About · Ministries ·
Sermons · Calendar**. Missions is *not* a peer of Ministries — missions is a ministry, so it
lives at `/ministries/missions`. `CONTENT_LOG.md` records every content and structure change
made outside of code, and is the first thing to read before re-running a migration.

**I'm New and Get Involved are two different jobs, and were once the same page.** Canvas pages
3 and 11 held identical content under two names, and page 11's `h1` was "Get Involved at MCC" —
it was Get Involved content wearing the wrong label. Page 11 became `/get-involved`, page 3 was
retired (`scripts/ia-get-involved-merge.php`), and a real `/im-new` was built for first-time
visitors: service times, what a Sunday looks like, kids, and how to find the building
(`scripts/ia-im-new-page.php`). Keep them distinct — I'm New is for someone who has never
walked in, Get Involved is for someone deciding where to serve.

- **Don't put unverified specifics on `/im-new`.** Everything on it traces to something already
  on the site (the footer menus carry the address, times, phone; the ministry nodes establish
  the nursery, Kids Worship, Sunday School and youth; the recurring calendar event establishes
  the Facebook Live stream). Parking, which door to use, how long the service runs and the
  communion practice are **not** recorded anywhere and are not safe to invent about a real
  congregation — `ia-im-new-page.php` has a commented `PARKING` constant for when the church
  confirms.

- **One primary menu: `main`.** `mcc_theme_preprocess_page()` reads it. There used to be a
  second `header-nav` menu that only the theme read, so the menu an editor reaches first at
  `/admin/structure/menu` did nothing to the page. Don't reintroduce a theme-private nav menu.
- **Section landing pages are Canvas pages; listings are Views *blocks* embedded in them.** No
  View owns a route. Canvas discovers `views_block:*` as components automatically —
  `block.settings.views_block:*` carries `FullyValidatable` in core's schema, which is the gate
  `BlockComponentDiscovery::checkRequirements()` enforces — so this needs no glue code.
  Two deliberate exceptions: `/calendar` stays a controller (a Views display cannot produce the
  month grid), and `/about/leadership` keeps its Views page display because it is a two-view
  construction with custom templates and per-term render cache keys.
- **Every bundle has its own pathauto pattern.** The old `menu_path` pattern
  (`/[node:menu-link:parents:join-path]/[node:title]`) had *empty* selection criteria, so it
  applied to every bundle and tied each node's URL to its menu placement — adding a menu link
  silently rewrote aliases. It is deleted. Don't add a pattern without selection criteria.
- **Setting a `path` field inserts a second alias; it does not update the existing one.** This
  is true for both nodes and canvas pages, and it bites twice: `redirect.auto_redirect` hooks
  `redirect_path_alias_update()`, which never fires on an insert, and the stale alias then
  *shadows* any redirect you add by hand, because redirects are matched only after inbound path
  processing has resolved the alias. Always delete the old alias first, then write the 301.
  Pathauto's own `updateEntityAlias()` does update in place and does mint the redirect.
- **Point redirects at `/node/N` or `/page/N`, never at an alias.** Drupal resolves the
  canonical alias at request time, so they survive every future slug change.
- **Canvas validates component inputs strictly.** `items_per_page` must be null or an integer;
  the string `'none'` fails. `heading` is a rich-text prop needing
  `['value' => …, 'format' => 'canvas_html_inline']`, not a bare string.

Helper scripts, all idempotent and safe to re-run — which matters, because a migration
re-import re-applies the flat `/[node:title]` pattern and undoes the slugs:

- `scripts/ia-page-slugs.php` — IA slugs for the D7 utility pages, plus retirements.
- `scripts/ia-landing-copy.php` / `scripts/ia-landing-links.php` — landing page copy and CTAs.
- `scripts/canvas-realias.php` — move a Canvas page and 301 its old URL.
- `scripts/canvas-swap-component.php` — swap a component in a Canvas page's tree.

## Leadership and bio pages

`/who-we-are/our-leadership` and the `bio` full view are built from the *Leadership Structure* taxonomy — see the "Our Leadership and bio pages" section of `README.md`. When working on them:

- **Never hardcode a group's name, order, explainer or singular label.** Section order is the vocabulary's term order; the explainer is the term `description`; the eyebrow is `field_role_singular`; the wide featured card is `field_feature_group`. Adding a group, reordering the page, or rewriting what deacons do must all be possible from the admin UI alone.
- **Keep the listing two views.** `mcc_leadership_groups` lists terms, `mcc_leadership` lists one group's people from a contextual filter and is embedded per term. Collapsing them into a single view with a grouping field breaks the dual-role case, which is the whole reason for the shape.
- **A person's card has to be told which section it is in.** Drupal hands every embedded view the same cached node object, so a two-group person cannot work it out at render time — `_mcc_theme_leadership_cards()` passes the term in and adds it to the render cache key. Anything that renders these cards has to do the same or the "Also serves as" line will be wrong in a way that only shows up for four people.
- **Never render `field_email` or `field_phone_number` because a value exists.** They are excluded from every view display on purpose; `field_publish_contact` is the per-person consent gate, and it defaults to off.
- **Duplicate people are merged in `BioDuplicateMerger`, never de-duplicated in a template.** Two legacy records per person fold into one node with two terms, on `POST_IMPORT` for `mcc_bio`. If you add a pair, add it to `PAIRS` with the person's name — the name is the guard that stops the merge running against a record somebody has since repurposed.
- **Check pages while logged in, not just anonymously.** For a user with "access contextual links", Drupal wraps rendered entities in `.contextual-region`, which contextual.module gives `position: relative`. That wrapper becomes the containing block for anything absolutely positioned inside it — which collapsed every bio portrait to 0px tall for editors while the anonymous page looked perfect. `curl` and headless screenshots are both anonymous by default, so this class of bug is invisible to them. Use `ddev drush uli` and load the page with that session before believing a layout works.
- **Watch the alias when retiring a node.** `pathauto.pattern.menu_path` has *empty* selection criteria, so it regenerates an alias for every bundle on every save, bios included. Anything that unpublishes a node has to retire the alias **after** that save, or pathauto hands it straight back — and a live alias silently shadows a redirect, because redirects are matched after inbound path processing has already turned the alias into `/node/N`.

## Environment & Local Development

This codebase is a Composer-managed Drupal site. Local development uses `ddev`.

### GitHub Codespaces only

This project is developed in **GitHub Codespaces**, not on a local machine. DDEV runs inside the Codespace.

- **If you are running inside the Codespace environment:** you have full access to run `ddev drush`, `ddev composer`, and `ddev terminus` directly to add modules, run updates, configure the site, and manage it hands-on. Go ahead and use these tools to get work done.
- **If you are NOT running inside the Codespace environment** (e.g. operating on this repo from a local checkout or another context): limit yourself to editing **code and configuration files**. Do not attempt interactive work — no starting `ddev`, no running `drush`/`composer`/`terminus` commands, no assuming a running site or database exists. Make your changes as file edits, commit them, and let Codespaces/CI pick them up.

`.devcontainer/devcontainer.json` is the source of truth for how the Codespace is provisioned: a base Debian image with the `docker-in-docker` and DDEV's official `install-ddev` devcontainer features layered on. That feature setup was confirmed directly against the `ddev/ddev` source (not just docs) — if you change it, re-check `containers/devcontainers/install-ddev/` in that repo rather than assuming the pattern is still current.

Codespaces prebuilds are **not** configured. That's a repo Settings → Codespaces UI action, not something expressible in `devcontainer.json` or via `gh` — it's a manual, opt-in step (it consumes Codespaces storage quota) left to a human to decide on.

### Local environment (DDEV)

Run commands from the project root:

- Start or restart the local environment with `ddev start`, `ddev restart`, and `ddev stop`.
- Install PHP dependencies with `ddev composer install`.
- Open the site with `ddev launch`.
- Run Drush commands with `ddev drush <command>` such as `status`, `user:login`, `cache:rebuild`, and `update:db`.

DDEV project config lives in `.ddev/config.yaml`. Use `.ddev/config.local.yaml` for machine-specific overrides.

## Common Drupal workflows

- Add a module with `ddev composer require drupal/<project>`, then `ddev drush pm:enable --yes <module_machine_name>`, then `ddev drush cache:rebuild`.
- Apply database updates after code changes with `ddev drush update:db --yes`.
- Import repository configuration into the site with `ddev drush config:import --yes`.
- Export site configuration back to the repo with `ddev drush config:export --yes`.

## Guardrails

- Do not commit secrets or machine-local overrides such as `.env`, `settings.local.php`, or `.ddev/config.local.yaml`.
- Do not commit `vendor/` or uploaded files under `web/sites/*/files`.
- Do not edit Drupal core or contributed projects in place.
- Put custom code in `web/modules/custom` and `web/themes/custom`.
- Prefix custom modules, custom themes, and Single Directory Components (SDC) with `mcc_` (or `mcc-` for component/folder names) where possible.

## Workflow

- Small, incremental commits — don't batch unrelated changes together.
- Push after each commit rather than letting work pile up unpushed.
- Work directly on `main` for now. This is early-stage and low-complexity enough that feature branches would just add overhead; revisit this once multiple people or longer-running changes are involved.
- Keep this file and `README.md` up to date whenever an architectural decision is made (new tooling, new environment setup, etc.) — update them as part of finishing the work, not as an afterthought.

## Tooling reference

- `ddev drush <command>` — Drush, for site administration, config import/export, cache rebuilds, etc.
- `ddev composer <command>` — Composer, for adding/updating modules, themes, and dependencies.
- `node scripts/calendar-compare.mjs [YYYY-MM …]` — renders the `calendar_design.zip` reference and the live `/calendar` and `/calendar/print` pages in headless Chromium and diffs them side by side. It also asserts the print sheet is one Letter page with nothing clipped, and exits non-zero when it isn't. Run it after any change to the calendar components, `CalendarMonth`, or the print CSS. Runs on the Codespace host (no ddev); output lands in the gitignored `.calendar-compare/`.
- `ddev exec terminus <command>` — Terminus, giving access both to the Pantheon `dev`, `test`, and `live` environments of the legacy site (`mcc-church` on Pantheon) that we're migrating content from, and to the **mcc2026** sandbox (this rebuild's `dev` environment only — it has no test/live). Treat `mcc-church` `test`/`live` with care — these are real environments, not scratch space. Prefer read-only Terminus commands on them unless a change has been explicitly requested. Terminus is installed inside the ddev `web` container, not on the Codespace host — `ddev terminus` (without `exec`) is not a valid command.
- `ddev exec terminus remote:drush <site>.<env> -- <command>` — run drush against a remote Pantheon environment over SSH without a manual `ssh` session. Useful for `status`, `cache:rebuild`, `watchdog:show`, `sql:query`, etc. Arbitrary shell commands over that same SSH channel are rejected ("exec request failed on channel 0") — only specific allowed commands (drush, git, sql-cli, rsync/sftp) work.
- Creating a new Pantheon site (`terminus site:create <name> <label> <upstream-machine-name> --org=mcc`) requires `--org` — this account's sites live under the **mcc** organization (`terminus org:list` shows it, though it has been seen to report empty on a stale/first call in a session; retry before assuming there's no org). `terminus upstream:list` shows available upstreams; this project's composer.json matches `drupal-cms-composer-managed`.
- SSH access to Pantheon (git clone/push, `remote:drush`, rsync) needs a key registered to the account via `terminus ssh-key:add`. A fresh Codespace's container has no keys in `~/.ssh` — generate one before first use. **Pantheon rejects ed25519 keys** ("SSH keys of type 'ed25519' are not yet supported") — use `ssh-keygen -t rsa -b 4096`.
- Importing a DB dump into a remote environment without uploading it first: `zcat dump.sql.gz | ssh -p 2222 <env>.<site-id>@appserver.<env>.<site-id>.drush.in drush sql-cli` (get the exact host/user from `terminus connection:info <site>.<env>`). Syncing `web/sites/default/files`: `rsync -rlz --ipv4 -e 'ssh -p 2222' web/sites/default/files/ <env>.<site-id>@appserver.<env>.<site-id>.drush.in:files/`.

## Secrets & tokens

Before asking the user to log in to a CLI interactively, check whether a token is already provisioned as a Codespaces secret:

- Run `env | grep -iE "token|key|secret"` on the Codespace host to see what's already available (e.g. `PANTHEON_MACHINE_TOKEN`, `GITHUB_TOKEN`). Codespaces secrets land as host-level environment variables, not inside the ddev containers, so they need to be passed through explicitly, e.g. `ddev exec "terminus auth:login --machine-token=$PANTHEON_MACHINE_TOKEN"`.
- Never echo a token's value into a command you type out or into chat — reference it via its env var name so the literal value never appears in the transcript.
- **`ddev exec` reconstructs and prints the full expanded command line (including argv) when the command fails.** Passing a secret as a command-line argument — even referenced via `$VAR` — risks that value being echoed verbatim into the error output the moment anything goes wrong (seen firsthand: a failed `ddev exec bash -c '...' _ "$TOKEN"` printed the literal token in the tool output). Pipe secrets via stdin instead: `ddev exec bash -c 'terminus auth:login --machine-token="$(cat)"' <<< "$TOKEN"`.
- Only fall back to asking the user for a token/login if nothing suitable turns up in the environment.
- The Codespace's ambient `GITHUB_TOKEN` (what `gh` auths with by default) can push code and open PRs but returns 403 on `gh secret set` / `gh secret list` — managing a repo's Actions secrets needs a user-supplied fine-grained PAT scoped to that repo with "Secrets: Read and write".

## Deploys

Pushing to `main` on GitHub triggers `.github/workflows/deploy-pantheon.yml`, which pushes the
same commit (source only — no `vendor/`, no `web/core`) to the **mcc2026** Pantheon sandbox
site's git remote. Pantheon's own Integrated Composer build step (`build_step: true` in
`pantheon.upstream.yml`) then runs `composer install` server-side and deploys the result to the
`dev` environment. There's no separate manual deploy step to remember — just make sure what you
push is something you'd want built and deployed. mcc2026 is a sandbox for testing this
migration/rebuild; it is not the church's production site.

Gotchas discovered getting this working (2026-07-29), worth knowing if the pipeline ever needs
touching again:

- **Pantheon's `dev` environment tracks the `master` branch, not `main`**, even on a freshly
  created site whose own repo defaults to `main` (`origin/HEAD -> origin/main`). Pushing to
  `main` succeeds silently but Pantheon's pre-receive hook prints "Skipping code sync, no
  Multidev environments were found for branch main" and never builds. Always push
  `<local-branch>:master`.
- **The target environment must be in git connection mode**, not sftp, or the push is flatly
  rejected ("pre-receive hook declined"). `terminus connection:set mcc2026.dev git` before
  pushing code; the CI workflow assumes this is already set (it doesn't set it itself).
- **`pantheon.upstream.yml` is upstream-owned — don't edit it.** It ships with the Drupal CMS
  core upstream scaffold and defaults `php_version` to whatever that upstream's maintainers
  pinned (8.3 as of this writing). Site-level overrides belong in `pantheon.yml` instead (see
  the one at the repo root, which pins `php_version: 8.4` to match `.ddev/config.yaml`'s
  `php_version`). If you ever bump ddev's PHP version, check whether `pantheon.yml` needs to
  follow — a mismatch fails the Pantheon build with an unsatisfiable-platform-requirement error
  from Composer (composer.lock resolved under one PHP version can lock packages that require a
  newer minimum than the other environment provides).
- **After any deploy that adds/removes modules or plugins, rebuild cache on the remote
  environment**: `ddev exec terminus remote:drush mcc2026.dev -- cache:rebuild`. A build can
  succeed while the site still 500s with `PluginNotFoundException` because Drupal's plugin
  discovery cache in the database is stale from before the new code landed — this isn't a build
  failure, just a cache that needs an explicit kick.
- `web/sites/default/settings.php` and `services.yml` are committed (not ddev-generated-only)
  specifically so Pantheon has something to boot from — don't re-gitignore them. The
  `IS_DDEV_PROJECT`-guarded block in `settings.php` is ddev-only; anything that must also apply
  on Pantheon (like `config_sync_directory`) needs to sit outside that guard.

## References

- [Drupal Best Practices Guide](file:///home/vscode/.gemini/antigravity-cli/brain/620318d3-e3c4-43af-a8a5-c6e27cafe3dc/drupal_best_practices.md)
- https://docs.ddev.com/en/stable/
- https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush
