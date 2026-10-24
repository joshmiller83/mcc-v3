# Deploys

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

Every push to `main` on GitHub deploys to the **mcc2026** Pantheon sandbox automatically, in two
stages:

1. **GitHub Actions** (`.github/workflows/deploy-pantheon.yml`) pushes that commit — source
   only, no `vendor/`, no `web/core` — to the mcc2026 site's git remote (`main` locally →
   `master` on Pantheon; see gotchas below for why).
2. **Pantheon's Integrated Composer** (`build_step: true` in `pantheon.upstream.yml`) then runs
   `composer install` server-side and deploys the result to the `dev` environment.

There's no manual deploy step — pushing to `main` *is* the deploy. mcc2026 is a sandbox for
testing this migration/rebuild; it is not the church's production site.

**Tracking a deploy after pushing:**

```sh
# 1. GitHub Actions — did the push reach Pantheon?
gh run list --repo joshmiller83/mcc-v3 --workflow=deploy-pantheon.yml -L 3
gh run watch <run-id> --repo joshmiller83/mcc-v3 --exit-status   # follow one to completion
#    gh was not installed on the Codespace host on 2026-10-01. The Actions API answers the
#    same question with the ambient token (reference it by name; never print the value):
curl -s -H "Authorization: Bearer $GITHUB_TOKEN" \
  "https://api.github.com/repos/joshmiller83/mcc-v3/actions/runs?per_page=3" \
  | grep -E '"(display_title|status|conclusion)"'

# 2. Pantheon build — did composer install / the site build succeed?
ddev exec terminus workflow:list mcc2026 --format=table | head -6   # look for "Sync code on dev"

# 3. If the build succeeded but the site still errors, the plugin/service cache is probably
#    stale (see gotcha below) — rebuild it and recheck:
ddev exec terminus drush mcc2026.dev -- cache:rebuild
curl -s -o /dev/null -w "%{http_code}\n" https://dev-mcc2026.pantheonsite.io/

# 4. A CSS/JS-only change deploys green and still serves the OLD file, silently — Drupal
#    keeps serving the aggregate it already built. Don't trust a 200; diff the bytes the
#    browser actually gets against the source:
curl -s https://dev-mcc2026.pantheonsite.io/<path> | grep -o 'files/css/css_[A-Za-z0-9_-]*\.css'
#    then fetch that aggregate and grep it for a rule the change introduced. A cache:rebuild
#    is what mints a new aggregate hash.
```

A GitHub Actions run typically finishes in ~15s (it's just a git push); the Pantheon build
that follows is the slow part — 2-4 minutes for `composer install` across this project's
dependency tree. Check step 1 first since it fails fast; only move to step 2 once it's green.

**Gotchas, worth knowing if the pipeline ever needs touching again:**

- **Pantheon's `dev` environment tracks the `master` branch, not `main`**, even on a freshly
  created site whose own repo defaults to `main` (`origin/HEAD -> origin/main`). Pushing to
  `main` succeeds silently but Pantheon's pre-receive hook prints "Skipping code sync, no
  Multidev environments were found for branch main" and never builds. Always push
  `<local-branch>:master` — the CI workflow already does this.
- **The target environment must be in git connection mode**, not sftp, or the push is flatly
  rejected ("pre-receive hook declined"). `terminus connection:set mcc2026.dev git` before
  pushing code by hand; the CI workflow assumes this is already set (it doesn't set it itself).
- **`pantheon.upstream.yml` is upstream-owned — don't edit it.** It ships with the Drupal CMS
  core upstream scaffold and defaults `php_version` to whatever that upstream's maintainers
  pinned (8.3 as of this writing). Site-level overrides belong in `pantheon.yml` instead (see
  the one at the repo root, which pins `php_version: 8.5` to match `.ddev/config.yaml`'s
  `php_version`). If you ever bump ddev's PHP version, check whether `pantheon.yml` needs to
  follow — a mismatch fails the Pantheon build with an unsatisfiable-platform-requirement error
  from Composer (composer.lock resolved under one PHP version can lock packages that require a
  newer minimum than the other environment provides).
- **After any deploy that adds/removes modules or plugins, rebuild cache on the remote
  environment** (see step 3 above). A build can succeed while the site still 500s with
  `PluginNotFoundException` because Drupal's plugin discovery cache in the database is stale
  from before the new code landed — this isn't a build failure, just a cache that needs an
  explicit kick.
- **After a burst of pushes, check that the *newest* "Sync code" succeeded, not just one of
  them.** On 2026-10-24 four commits went up a few minutes apart. The sync for the last one
  started at 15:00:45 while the previous one was still building (it finished at 15:01:27), and
  failed 23 seconds in at a platform step, before any build. Overlap is the likely cause;
  that is inferred from the timing, not confirmed. The commit was still in Pantheon's git
  log, but the deployed build was the one before it. It was docs only, so nothing was lost;
  a code commit would have stayed undeployed until the next push. If the newest sync failed,
  wait until none is running and push again.
- **A green "Sync code" does not mean the config import ran — check `config:status` on the
  remote.** Seen 2026-09-21: every web request to mcc2026, a static PNG included, returned
  Pantheon's edge page `504 - Target in maintenance`, with a `lock_trial_site` workflow in
  `terminus workflow:list` from 13 days earlier. The code sync still succeeded and drush still
  bootstrapped, so the PHP change was live — but the display config in the same push was not, and
  `terminus drush mcc2026.dev -- config:status` listed it as Different. The Quicksilver import is a
  `webphp` operation, so it most likely cannot run while the web tier is down; that link is
  inferred, not confirmed. When this happens, **set the keys your own commit changed** with
  `config:set` rather than running a full `config:import` by hand: the tip usually carries other
  drift (two Canvas items that day), the tip is the source of truth, and a full import stomps it.
  A 504 whose body says "Target in maintenance" is a Pantheon account matter, not a code one —
  don't go looking for it in the diff.
- **A push that carries update hooks needs `updatedb` before `config:import`, which is why the
  Quicksilver hook runs `drush deploy`** (see [Config sync](config-sync.md)). When the web tier
  is down and the hook cannot run, do the same by hand, in the same order, once the environment
  is back: `ddev exec terminus drush mcc2026.dev -- deploy -y`, or step by step `updatedb -y`,
  `config:import -y`, `cache:rebuild`. Run the descending sync first if the tip may have been
  edited since `main` was last reconciled with it, because that import is a full one.
  `updatedb:status` and `config:status` should both come back empty afterwards. Learned from
  the 2026-10-01 dependency update, the first push to carry both update hooks and the config
  those hooks create, while the tip was still answering 504.
- `web/sites/default/settings.php` and `services.yml` are committed (not ddev-generated-only)
  specifically so Pantheon has something to boot from — don't re-gitignore them. The
  `IS_DDEV_PROJECT`-guarded block in `settings.php` is ddev-only; anything that must also apply
  on Pantheon (like `config_sync_directory`) needs to sit outside that guard.

