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
- `ddev exec terminus <command>` — Terminus, giving access to the Pantheon `dev`, `test`, and `live` environments of the legacy site (`mcc-church` on Pantheon) that we're migrating content from. Treat `test` and `live` with care — these are real environments, not scratch space. Prefer read-only Terminus commands (checking status, logs, backups) unless a change to those environments has been explicitly requested. Terminus is installed inside the ddev `web` container, not on the Codespace host — `ddev terminus` (without `exec`) is not a valid command.

## Secrets & tokens

Before asking the user to log in to a CLI interactively, check whether a token is already provisioned as a Codespaces secret:

- Run `env | grep -iE "token|key|secret"` on the Codespace host to see what's already available (e.g. `PANTHEON_MACHINE_TOKEN`, `GITHUB_TOKEN`). Codespaces secrets land as host-level environment variables, not inside the ddev containers, so they need to be passed through explicitly, e.g. `ddev exec "terminus auth:login --machine-token=$PANTHEON_MACHINE_TOKEN"`.
- Never echo a token's value into a command you type out or into chat — reference it via its env var name so the literal value never appears in the transcript.
- Only fall back to asking the user for a token/login if nothing suitable turns up in the environment.

## Deploys

Pushing to GitHub triggers the Pantheon build process automatically. There's no separate manual deploy step to remember — just make sure what you push is something you'd want built and deployed.

## References

- [Drupal Best Practices Guide](file:///home/vscode/.gemini/antigravity-cli/brain/620318d3-e3c4-43af-a8a5-c6e27cafe3dc/drupal_best_practices.md)
- https://docs.ddev.com/en/stable/
- https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush
