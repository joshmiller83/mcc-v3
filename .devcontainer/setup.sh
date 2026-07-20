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

# Install Antigravity CLI (agy)
curl -fsSL https://antigravity.google/cli/install.sh | bash
# Symlink agy to a system-wide PATH location
sudo ln -sf ~/.local/bin/agy /usr/local/bin/agy

# Install Claude Code CLI
sudo npm install -g @anthropic-ai/claude-code

