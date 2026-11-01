# 2026-11-01 — Cva deprecation patched, patches lock caught up

_A session update, indexed in [AGENTS.md](../../../AGENTS.md#updates)._

- **The three "Using null as an array offset" notices from `Twig\Extra\Html\Cva->apply()`
  are gone.** Twig fixed this upstream on 2026-10-24 but hadn't released the fix, so it's
  applied as `patches/local/twig-html-extra-cva-null-offset.patch` until a release past
  3.29.0 includes it. Checked with the pages that had logged it (`/`, `/im-new`), no new
  watchdog rows, and a direct `apply()` with null recipe values under `error_reporting=-1`.
  The rule, including when and how to drop the patch, is in
  [dependency-updates.md](../dependency-updates.md).
- **`patches.lock.json` had been stale since the 2026-10-24 update.** project_browser 2.1.5
  declares a second patch of its own, and Pantheon skipped it until this relock. It went in as
  its own commit. Relocking after every `composer update` is now part of the patches rule in
  [dependency-updates.md](../dependency-updates.md).

## Left open

- Watch for a twig/html-extra 3.x release after 3.29.0, then drop the patch.
