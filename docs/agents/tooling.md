# Tooling reference

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

- `ddev drush <command>` — Drush, for site administration, config import/export, cache rebuilds, etc.
- `ddev composer <command>` — Composer, for adding/updating modules, themes, and dependencies.
- `vendor/bin/dr canvas:doctor --checks=config --no-cache --details`, inside the web container
  (`ddev exec bash -s <<'EOF'` … `cd /var/www/html` …) — Canvas's integrity check over every
  Canvas config entity (`--checks` also takes content and auto-save checks). It is a command
  for core's console runner, not drush. See
  [dependency-updates.md](dependency-updates.md) for what it caught and which findings are known.
- `node scripts/calendar-compare.mjs [--month YYYY-MM …]` — renders the `calendar_design.zip` reference and the live `/calendar` and `/calendar/print` pages in headless Chromium and diffs them side by side. It also asserts the print sheet is one Letter page with nothing clipped, and exits non-zero when it isn't. Run it after any change to the calendar components, `CalendarMonth`, or the print CSS — and after an import that adds events, against the months that gained them, since a busier month is as able to break the one-page fit as a CSS change. Months are `--month` flags, repeatable; a bare `2026-11` throws `Unknown argument` (this line and the README both used to show the bare form). Runs on the Codespace host (no ddev); output lands in the gitignored `.calendar-compare/`.
- **A month's print sheet as a PDF** is a by-product of that same check: run it for the month
  and copy `.calendar-compare/drupal-print-YYYY-MM.pdf` to `exports/mcc-calendar-YYYY-MM.pdf`.
  It is Chromium's own print output (Letter, zero margins, backgrounds on, print media
  emulated), so it is what the office gets from the browser's Print button, not a screenshot.
  `exports/` is committed so a sheet can be handed on from the repo; October 2026 was the first.
- `ddev exec terminus <command>` — Terminus, giving access both to the Pantheon `dev`, `test`, and `live` environments of the legacy site (`mcc-church` on Pantheon) that we're migrating content from, and to the **mcc2026** sandbox (this rebuild's `dev` environment only — it has no test/live). Treat `mcc-church` `test`/`live` with care — these are real environments, not scratch space. Prefer read-only Terminus commands on them unless a change has been explicitly requested. Terminus is installed inside the ddev `web` container, not on the Codespace host — `ddev terminus` (without `exec`) is not a valid command.
- `ddev exec terminus drush <site>.<env> -- <command>` — run drush against a remote Pantheon environment over SSH without a manual `ssh` session. Useful for `status`, `cache:rebuild`, `watchdog:show`, `sql:query`, etc. Arbitrary shell commands over that same SSH channel are rejected ("exec request failed on channel 0") — only specific allowed commands (drush, git, sql-cli, rsync/sftp) work.
- Creating a new Pantheon site (`terminus site:create <name> <label> <upstream-machine-name> --org=mcc`) requires `--org` — this account's sites live under the **mcc** organization (`terminus org:list` shows it, though it has been seen to report empty on a stale/first call in a session; retry before assuming there's no org). `terminus upstream:list` shows available upstreams; this project's composer.json matches `drupal-cms-composer-managed`.
- SSH access to Pantheon (git clone/push, `drush`, rsync) needs a key registered to the account via `terminus ssh-key:add`. A fresh Codespace's container has no keys in `~/.ssh` — generate one before first use. **Pantheon rejects ed25519 keys** ("SSH keys of type 'ed25519' are not yet supported") — use `ssh-keygen -t rsa -b 4096`.
- The key has to live on the *Codespace host* (`~/.ssh/id_rsa`) and then be loaded into ddev's ssh-agent with `ddev auth ssh` — the web container doesn't mount the host's `~/.ssh`. `Permission denied (publickey)` from `terminus drush` almost always means the agent is empty, not that the key is unregistered. **`ddev auth ssh` recreates the containers**, so run it on its own and let them settle; a `ddev exec` issued in the same breath gets killed mid-flight (exit 143). The agent also empties whenever the containers restart, so re-run it after any `ddev restart` — and after the Codespace itself is stopped and resumed, which leaves the containers `exited` for `ddev start` to bring back. Seen 2026-09-21: after a stop/resume, uncommitted edits, Terminus's login and Playwright's Chromium (plus its apt libraries) all survived; the ssh-agent did not, so the first `terminus drush` failed with `Permission denied (publickey)`, and the session scratchpad under `/tmp` was gone.
- Importing a DB dump into a remote environment without uploading it first: `zcat dump.sql.gz | ssh -p 2222 <env>.<site-id>@appserver.<env>.<site-id>.drush.in drush sql-cli` (get the exact host/user from `terminus connection:info <site>.<env>`). Syncing `web/sites/default/files`: `rsync -rltz --ipv4 -e 'ssh -p 2222' web/sites/default/files/ <env>.<site-id>@appserver.<env>.<site-id>.drush.in:files/`. **Keep the `-t`.** Without it rsync doesn't preserve mtimes, its size-plus-mtime quick check never matches, and every run re-transfers every file — a pull with `-rlz` moved all 351 MB of the legacy site's files onto a directory that already held nearly all of them.
- **Anything with nested quotes goes to `ddev exec` over stdin, not as `bash -c '…'`.** `ddev exec` re-wraps its arguments in a double-quoted `bash -c "…"`, so a single-quoted `sed 's,a,b,'` inside your command arrives unquoted and `sed` parses the replacement as its own options. Use `ddev exec bash -s <<'EOF' … EOF` — the quoted heredoc reaches the container byte for byte, and as a bonus the script body never appears in the command line `ddev exec` prints on failure.
- **Inside such a heredoc, give every `terminus drush` (and anything else over SSH) `< /dev/null`.**
  The SSH session inherits the heredoc as its stdin and reads the rest of the script, so the
  first remote command runs and every line after it silently never does — exit 0, no error, just
  missing output. Seen 2026-10-24 on a three-query check of the tip.
- **Terminus's login does not survive the containers being recreated on a fresh Codespace** — `terminus auth:whoami` reporting "You are not logged in" after a rebuild is normal, not a revoked token. Log back in over stdin (see [Secrets & tokens](../../AGENTS.md#secrets--tokens)). It *does* survive `ddev auth ssh`.
- **A one-off Playwright probe goes in `.ddev/.downloads/`, not the session scratchpad.** The
  `.mjs` scripts `import { chromium } from 'playwright'`, which an ES module resolves by walking
  up from its own directory to the repo's `node_modules`; from `/tmp` it is not found, and
  `NODE_PATH` does not apply to ES modules. `.ddev/.downloads/` is gitignored and is where the
  earlier probes live.
- **The Codespace host's `python3` has no `json` module** (`ModuleNotFoundError`, seen
  2026-10-24). Read `composer.json`, `composer.lock` and API responses with `jq`, which is
  installed.

## References

- [Drupal Best Practices Guide](file:///home/vscode/.gemini/antigravity-cli/brain/620318d3-e3c4-43af-a8a5-c6e27cafe3dc/drupal_best_practices.md)
- https://docs.ddev.com/en/stable/
- https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush
