# Config sync

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

mcc2026 (Pantheon) is the source of truth for this rebuild, not `main` — the git repo is a
mirror of it. The **tip** is whichever environment currently holds the working version:
`dev` today, moving to `test` and then `live` as the project is promoted. Active config on
the tip (its database) can drift from what's checked into `main`'s `config/sync/` — from UI
edits, or a migration/content script run directly against it via `terminus drush` —
and code can just as easily land on the tip ahead of the config it depends on. Both
directions are handled now, so neither should require remembering to do it by hand:

- **Tip → `main` (descending):** `scripts/sync-config-from-tip.sh [env]` (`env` defaults to
  `dev`). It pulls a live DB from that Pantheon environment into local ddev (full DB, no
  files — this is about config+content parity, not asset storage), runs
  `drush config:export`, and if that surfaces any drift, commits it to a
  `chore/config-sync-<env>-<date>` branch and pushes it for you to review and open a PR
  from. If there's no drift, it says so and cleans up — nothing to merge.
  **Run this, and get the resulting PR merged (or confirm it found nothing), before cutting
  any new feature/fix branch.** That's what keeps a feature branch's own `config:export` diff
  showing *only* that feature's intended changes — if it shows anything else, baseline drift
  wasn't reconciled first; re-run the script and rebase.
  - Not every drift the script finds is safe to blindly merge — read the diff. E.g.
    `canvas.component.sdc.*` component entries carry versioned prop schemas
    (`versioned_properties.active` plus older hash-keyed snapshots); a real schema change on
    the tip is fine to bring in, but check whether it's the tip catching up to reality (safe)
    versus something that shouldn't be sitting in that environment's database at all.
- **`main` → tip (ascending), automatic:** a Pantheon Quicksilver hook in `pantheon.yml`
  (`workflows.sync_code` and `workflows.deploy`) runs
  `private/scripts/quicksilver/config-import.php`, which is `drush deploy -y` — `updatedb`,
  then `config:import`, then `deploy:hook`, then `cache:rebuild`, in that order — server-side
  after every code sync (push to `main`, today) and every environment promotion (dev→test,
  test→live, once that starts happening). There's no manual `drush updatedb` or `drush cim`
  step anymore. Caveats:
  - **It is `drush deploy`, not `config:import` alone, and must stay that way.** Until
    2026-10-01 the hook ran only `config:import` and `cache:rebuild`, which held up while no
    push carried a `hook_update_N`. The 2026-10-01 dependency update was the first that did —
    ai 1.5.0, eca 3.1.9 and trash 3.1.0 all ship update hooks, and ai's is what *creates*
    `ai.html_to_markdown.settings` — so the old hook would have imported that config over a
    database whose updates had not run, and left them pending on the tip until somebody read
    the status report. `deploy` runs the four steps in the order core documents.
  - `drush deploy` is idempotent, so it's harmless if `sync_code` ever fires twice for one
    push (a known Integrated Composer quirk).
  - This makes the descending sync load-bearing: the hook applies whatever `main` has, no
    questions asked. If `main` wasn't actually reconciled with the tip before a feature
    merged, the auto-import ships that gap right along with the feature.
  - `webphp` Quicksilver operations have a 120s timeout, and `updatedb` now counts against
    it. The 2026-10-01 update's ten hooks ran in seconds locally; a slow data-migrating hook
    is the thing to watch for.
  - Check it fired: `ddev exec terminus workflow:list mcc2026 --format=table` after a push,
    look for "Deploy (updatedb, config import, cache rebuild)..." under the
    `Sync code on "dev"` workflow. If the web tier was down when the push landed (the 504 in
    [Deploys](deploys.md)), assume it did not run and run `drush deploy -y` there by hand.

