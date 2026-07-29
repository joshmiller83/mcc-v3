#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVICES_FILE="$ROOT/web/sites/default/services.yml"

cat >"$SERVICES_FILE" <<'YAML'
parameters:
  twig.config:
    debug: true
    auto_reload: true
    cache: false
YAML

cd "$ROOT"
ddev drush cache:rebuild -y >/dev/null
echo "Twig debug comments enabled locally via web/sites/default/services.yml"
