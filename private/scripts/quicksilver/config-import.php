<?php

/**
 * @file
 * Quicksilver: bring the environment's database up to date after a code sync.
 *
 * `drush deploy` runs, in order: updatedb (the update hooks the new code ships),
 * config:import, deploy:hook (post-import hooks), cache:rebuild. Running
 * config:import alone — which this script did until 2026-10-01 — imports config
 * against a database whose schema the pending update hooks have not touched yet,
 * so a dependency update that carries both (the 2026-10-01 one did: ai, eca and
 * trash all shipped update hooks) would import ahead of its own updates.
 */

echo "Deploying: updatedb, config:import, deploy:hook, cache:rebuild...\n";
passthru('drush deploy -y', $status);
if ($status !== 0) {
  echo "drush deploy exited with status $status\n";
  exit($status);
}
