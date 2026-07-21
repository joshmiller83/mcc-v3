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
ddev poweroff || true

echo "Installing Antigravity CLI (agy)..."
# Install Antigravity CLI (agy)
curl -fsSL https://antigravity.google/cli/install.sh | bash
# Symlink agy to a system-wide PATH location
sudo ln -sf ~/.local/bin/agy /usr/local/bin/agy

echo "Installing Claude Code CLI..."
# Install Claude Code CLI via the official native installer (npm method is deprecated)
curl -fsSL https://claude.ai/install.sh | bash
# Symlink claude to a system-wide PATH location
sudo ln -sf ~/.local/bin/claude /usr/local/bin/claude

echo "Tool setup complete."


