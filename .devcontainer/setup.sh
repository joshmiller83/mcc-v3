#!/usr/bin/env bash
set -ex

wait_for_docker() {
  while true; do
    docker ps > /dev/null 2>&1 && break
    sleep 1
  done
  echo "Docker is ready."
}

wait_for_docker

# Avoid errors on rebuilds where some containers are kept around.
ddev poweroff

# `ddev start -y` is intentionally not run here yet — no DDEV project is
# configured in this repo yet (no .ddev/config.yaml). Add it back once the
# Drupal CMS install exists.
