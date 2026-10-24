# Environment and local development

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

This codebase is a Composer-managed Drupal site. Local development uses `ddev`.

## GitHub Codespaces only

What an agent may run depends on whether it is inside the Codespace — see "Where this runs" in
[AGENTS.md](../../AGENTS.md#where-this-runs).

`.devcontainer/devcontainer.json` is the source of truth for how the Codespace is provisioned: a base Debian image with the `docker-in-docker` and DDEV's official `install-ddev` devcontainer features layered on. That feature setup was confirmed directly against the `ddev/ddev` source (not just docs) — if you change it, re-check `containers/devcontainers/install-ddev/` in that repo rather than assuming the pattern is still current.

Codespaces prebuilds are **not** configured. That's a repo Settings → Codespaces UI action, not something expressible in `devcontainer.json` or via `gh` — it's a manual, opt-in step (it consumes Codespaces storage quota) left to a human to decide on.

## Google Drive sync (`~/gdrive`)

`postCreateCommand` in `.devcontainer/setup.sh` mirrors a shared Google Drive folder — design
handoffs, feedback recordings, anything that shouldn't go into git — down to `~/gdrive` on every
rebuild, via `rclone` and a service account. No OAuth flow, no browser consent — it authenticates
non-interactively with a service-account key, which is what makes it possible to run unattended in
`postCreateCommand`.

- **Two Codespaces secrets drive it:** `GDRIVE_SA_KEY` (the service account's JSON key, written to
  `~/.config/rclone/gdrive-sa.json`) and `GDRIVE_FOLDER_ID` (the Drive folder's id, used as
  `root_folder_id` in the generated `~/.config/rclone/rclone.conf`). If either is unset,
  `setup.sh` skips the sync entirely rather than failing the rebuild.
- **The target folder must be shared with the service account's email** — Viewer access is
  enough — or the sync silently does nothing. Get the exact address from the key itself:
  `jq -r .client_email ~/.config/rclone/gdrive-sa.json`.
- **An empty `~/gdrive` after rebuild does not mean the script failed.** `setup.sh` swallows
  `rclone sync` failures (`|| echo "..."`), so `postCreateCommand` reports success either way —
  and even a direct `rclone lsjson gdrive:` returns an empty list with **no error** both when the
  folder is genuinely empty *and* when `GDRIVE_FOLDER_ID` is wrong or was never shared with the
  service account. Google's Drive API doesn't validate the parent id on a list-children call, so
  the two cases look identical. Confirm sharing on the folder itself before assuming the sync
  script is broken.
- **To re-sync by hand** (no rebuild needed): `rclone sync gdrive: ~/gdrive --fast-list`.
  `rclone about gdrive:` confirms the service account credentials are valid; it does not confirm
  folder access, since `about` reports the service account's own tiny drive, not the shared
  folder's contents.

## Local environment (DDEV)

Run commands from the project root:

- Start or restart the local environment with `ddev start`, `ddev restart`, and `ddev stop`.
- Install PHP dependencies with `ddev composer install`.
- Open the site with `ddev launch`.
- Run Drush commands with `ddev drush <command>` such as `status`, `user:login`, `cache:rebuild`, and `update:db`.

DDEV project config lives in `.ddev/config.yaml`. Use `.ddev/config.local.yaml` for machine-specific overrides.

## Refreshing local from both sites

The local ddev database server holds **two** databases, and "pull the latest" means both:

| Local database | Comes from | Read by |
| -------------- | ---------- | ------- |
| `db` | `mcc2026.dev` — the new site | Drupal itself |
| `legacy` | `mcc-church.live` — the D7 source site | the `migrate` connection in `settings.php`, i.e. every `mcc_migration` source plugin |

Both sites' public files land in the one `web/sites/default/files`, because the migrations read
the D7 files from there too.

```sh
git pull --ff-only origin main
ddev snapshot --name pre-pull-$(date +%Y%m%d)        # both pulls overwrite; this is the undo

# 1. New site -> `db`. No SSH key needed; it dumps over the MySQL port.
ddev pull pantheon --environment="DDEV_PANTHEON_SITE=mcc2026,DDEV_PANTHEON_ENVIRONMENT=dev" --skip-files -y

# 2. Source site -> `legacy`. Read-only, and --single-transaction takes no locks on the real site.
ddev exec bash -s <<'EOF'
set -euo pipefail
cd /var/www/html/.ddev/.downloads
DUMP=$(terminus connection:info mcc-church.live --field=mysql_command \
  | sed 's,^mysql,mysqldump --no-autocommit --single-transaction --opt -Q,')
eval "$DUMP" | gzip > legacy.sql.gz
zcat legacy.sql.gz | tail -1        # must print "-- Dump completed on …", or it was truncated
EOF
ddev import-db --database=legacy --file=.ddev/.downloads/legacy.sql.gz

ddev drush cache:rebuild && ddev drush config:status
```

- **The `cache:rebuild` at the end is load-bearing, not hygiene.** The pulled database carries
  Pantheon's discovery caches, and the SDC component definitions in them hold absolute paths under
  `/code/web/…`. Until the rebuild, every component render logs
  `file_get_contents(/code/web/themes/custom/mcc_theme/components/…): Failed to open stream` and
  the page comes out without that component — the calendar print sheet, the footer. Skipped on
  2026-10-01, this failed the first `calendar-compare` run on all 61 months ("no sheet element
  matching .mcc-print-sheet", 428 warnings in the watchdog) for a reason that had nothing to do
  with the calendar. Drush itself is unaffected, so the migration ran fine on the same caches;
  only rendered pages lie. Rebuild first, then render anything.
- **`ddev pull pantheon` can only ever fill `db`.** There is no provider flag for a second target
  database, which is why the source site is a hand-rolled dump plus `ddev import-db --database=legacy`.
  That dump is the same command the provider builds for itself, so the two stay equivalent.
- **Files need the SSH key and agent** (see [Tooling reference](tooling.md)); the two
  database pulls do not. Pull the **source site's files first and the new site's second**, so
  where a filename exists on both the new site's copy wins. No `--delete` — the directory is a
  union of two sites, and `--delete` against either one removes the other's files. Skip `css/`,
  `js/`, `php/` and `styles/`; they are generated and rebuild locally.
- **Pulling the files, as run on 2026-10-01** — inside the web container, where the agent and
  Terminus both are (`ddev exec bash -s <<'EOF'`), with `U` and `H` read from
  `terminus connection:info mcc-church.live --field=sftp_username` and `--field=sftp_host`:

  ```sh
  rsync -rltz --ipv4 -e 'ssh -p 2222 -o StrictHostKeyChecking=accept-new' \
    --exclude=css/ --exclude=js/ --exclude=php/ --exclude=styles/ \
    "$U@$H:files/" web/sites/default/files/          # then once more for mcc2026.dev
  ```

  A locked tip does not close any of these doors: on 2026-10-01 every web request to mcc2026.dev
  still answered the `504` edge page, and `ddev pull pantheon`, `connection:info` and the SFTP
  rsync all worked against it anyway. "Frozen" means the web tier, not the data.
- **Pulling is not migrating.** A fresh `legacy` changes nothing on the site until an import is
  run, and that is a separate decision with its own rules — see [The D7 migration](d7-migration.md).
  Do **not** reach for `migrate:import --group=mcc --update`.
- **`ddev snapshot restore` replaces the whole database server, not just `db`.** `legacy` goes back
  to what it was when the snapshot was taken, and any scratch database created since (a
  `tip_before` loaded for comparison, say) is gone. Re-import it if you still need it.
- **`config:status` after the pull is the cheap drift check.** Anything it lists is active config
  on the tip that `main` doesn't have; `scripts/sync-config-from-tip.sh` is what reconciles it.

## Common Drupal workflows

- Add a module with `ddev composer require drupal/<project>`, then `ddev drush pm:enable --yes <module_machine_name>`, then `ddev drush cache:rebuild`.
- Apply database updates after code changes with `ddev drush update:db --yes`.
- Import repository configuration into the site with `ddev drush config:import --yes`.
- Export site configuration back to the repo with `ddev drush config:export --yes`.

