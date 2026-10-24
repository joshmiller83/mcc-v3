# Dependency updates

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

How the 2026-10-01 update went (core 11.4.4→11.4.8, Canvas 1.8→1.12, 82 updates and 14
installs in all, inside the existing constraints, then four major bumps on top), and what to
repeat next time:

- **Reconcile the tip first, then snapshot, then update.** `scripts/sync-config-from-tip.sh`
  found two Canvas items of drift that day; merged first, the update's own `config:export`
  held only the update. `ddev snapshot --name pre-update-<date>` is the undo. The order of
  work after that: `composer update --with-all-dependencies`, check `PATCHES.txt` in each
  patched package, `updatedb -y`, `updatedb:status`, `cache:rebuild`, `config:export -y`, then
  the page checks, `scripts/calendar-compare.mjs`, every idempotent script in this file, the
  Canvas Doctor (below), and the Canvas editor plus a node edit form through a `drush uli`
  session.
- **Expect `config:export` to show Canvas discovery, not drift.** Canvas registers every block
  plugin and component source it finds as a `canvas.component.*` config entity and files it in
  a `canvas.folder.*`, so a module update that adds a block plugin (eca 3.1.9's
  `project_browser_block:eca_guide_library`) or Canvas adding a source (1.12's `marker`)
  arrives as new config. Commit it; left out, Canvas recreates it on the next cache rebuild
  and `config:status` on the tip reads Different forever.
- **Every Canvas item sits in exactly one folder, and each folder name appears once per type.
  Check with `vendor/bin/dr canvas:doctor --checks=config --no-cache --details` after any
  Canvas update or folder change.** Canvas 1.12 brought a health check, the Doctor, which
  cron runs 50 items at a time. Validating a folder *throws* rather than reporting when an
  item is in two folders: `RuntimeException: It is impossible for an item to exist in multiple
  Folders.` A throwing cron window never advances its cursor (`canvas.doctor.cron_cursor` in
  state), so from 2026-10-03 every cron run logged the same error, and the CLI Doctor died on
  it too, never reaching the rest of the config. The duplicates were there from install day:
  the Caresphere recipe ships 44 folder configs, six of them "core", six "Block" and six
  "Announcements", and Canvas added a seventh of each. 49 folders came down to 23 on
  2026-10-24, by merging each name into the folder holding the most items. A second way in is
  a component that Canvas rediscovers and files in its default folder while it still sits in
  the one you moved it to. That is how `views_block:mcc_ministries-group_ministries` ended up
  in both "Block" and "Lists (Views)", arriving with the 2026-10-01 export from the tip.
  Merging deletes folders, which `config:import` carries on its own: tested from a pre-merge
  snapshot, import alone gave the same 23. The Doctor is core's console runner
  (`vendor/bin/dr`), not drush; `ddev drush canvas:doctor` says "no commands defined".
  Seven findings it reports are known and inert: see the 2026-10-24 update.
- **A contrib update hook can skip this site and leave "Mismatched entity and/or field
  definitions" on the status report.** trash 3.1.0's hooks only touch a `deleted` field whose
  installed definition names `trash` as its provider; node's here says `node`, so both hooks
  walked past it. The fix is a `hook_update_N` in `mcc_core` (`mcc_core_update_11101`) that
  installs the current definition — it deploys through `drush deploy` to every environment —
  never a one-off `php:eval`, which leaves the tip mismatched.
- **"Update readiness checks" is about `cweagans/composer-patches`, and no version of it
  clears the error on its own.** Package Manager trusts a fixed list of plugins
  (`ComposerPluginsValidator`), composer-patches is not on it at any version, so it has to be
  named in `package_manager.settings: additional_trusted_composer_plugins` — config, exported
  and committed on 2026-10-01. Its own validator then wants the plugin as a root dependency
  with `extra.composer-exit-on-patch-failure: true` in `composer.json`. Both are in place; if
  the error comes back, those two are what to check first.
- **Patches go through composer-patches 2.x now, and `patches.lock.json` is part of the
  repo.** Patches still live under `extra.patches`, but 2.x applies them from the lock: after
  adding, removing or editing a patch run `composer patches-relock`, then `composer install`,
  and commit the lock, or Pantheon's build applies the old set. 2.x writes no `PATCHES.txt`;
  check the patched file itself (the hero-card component YAML has `subtitle:` and
  `side_media:`). The four majors first left for a human — composer-patches 2, editoria11y 3,
  tagify 2, ui_icons 2 — went in the same day once allowed: one `ui_icons` update hook, no
  config change, `pack:name` icon values untouched, tagify's one form display (sermon) and
  the icon picker both still render. editoria11y is installed code only; it is not enabled.
- **Take the watchdog marker (`MAX(wid)`) *after* any database pull.** A pull replaces the
  whole table, so a marker from before it compares the new history against the wrong number.

- **PHP security releases: Pantheon applies them itself; locally they need a rebuild.**
  `pantheon.yml` names only the minor version, and Pantheon rolls out each patch release on its
  own. DDEV's webserver image carries whatever PHP was current when that DDEV release was built
  (v1.25.4: 8.5.9 from 2026-07-30, when 8.5.12 shipped on 10-21), so
  `.ddev/web-build/Dockerfile.php-security` upgrades the active version's packages from
  deb.sury.org at image build time. Docker caches that layer: after a PHP security release, run
  `ddev utility rebuild` (no cache by default) and check `ddev exec php -v` against
  `curl -s "https://www.php.net/releases/index.php?json&version=8.5" | jq -r .version`.
- **Start with `composer audit`, and read each advisory's affected range.** On 2026-10-24 it
  listed twelve against the lock — among them SA-CORE-2026-014, a critical remote code
  execution in core 11.4.8 — and every fix sat inside the existing constraints, so a plain
  `composer update --with-all-dependencies` cleared all twelve (55 updates). `ddev composer
  audit` exits 1 whenever it finds anything and ddev then prints only the failure; run it as
  `ddev exec bash -c 'cd /var/www/html && composer audit --format=json'` and read the JSON
  with `jq`. Note transitive majors in the log: `enshrined/svg-sanitize` went 0.22 → 1.1.1
  that day, allowed by its requirer, and it was the one carrying three of the CVEs.
- **On PHP 8.5, three deprecation notices are someone else's and expected.** "Using null as an
  array offset is deprecated" from core's `ContentEntityBase::hasTranslation()` (~50 per load
  of `/admin/content`; core issue 3580668, the admin theme's status-field template passes a
  langcode that isn't on the row), and the same notice from `twig/html-extra`'s
  `Cva::apply()` and `gin_login`'s theme negotiator. Drupal logs `E_DEPRECATED` at debug
  severity (7), so a `severity <= 4` watchdog filter hides them, while local development's
  verbose error display prints them on the page — they look like errors in a page sweep and
  aren't. Don't patch core for them; recheck after each update.
- **Lint contrib and custom code with the new PHP before switching to it.** Every PHP version
  is installed in the ddev image, so `php8.5 -l` over `web/modules`, `web/themes` and
  `web/profiles` finds compile-time deprecations (non-canonical casts, implicit nullable
  types) without touching the running site. On 2026-10-24 it flagged three contrib modules
  and nothing custom; only `easy_email` of those is enabled.
