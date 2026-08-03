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

echo "Setting up Google Drive sync..."
if [ -n "${GDRIVE_SA_KEY:-}" ] && [ -n "${GDRIVE_FOLDER_ID:-}" ]; then
  if ! command -v rclone > /dev/null 2>&1; then
    curl -fsSL https://rclone.org/install.sh | sudo bash
  fi

  mkdir -p ~/.config/rclone ~/gdrive
  echo "$GDRIVE_SA_KEY" > ~/.config/rclone/gdrive-sa.json

  cat > ~/.config/rclone/rclone.conf <<EOF
[gdrive]
type = drive
scope = drive
service_account_file = $HOME/.config/rclone/gdrive-sa.json
root_folder_id = $GDRIVE_FOLDER_ID
EOF
  chmod 600 ~/.config/rclone/rclone.conf ~/.config/rclone/gdrive-sa.json

  echo "Syncing Google Drive folder to ~/gdrive..."
  rclone sync gdrive: ~/gdrive --fast-list || echo "Google Drive sync failed — check GDRIVE_SA_KEY / GDRIVE_FOLDER_ID secrets and that the folder is shared with the service account."
else
  echo "GDRIVE_SA_KEY / GDRIVE_FOLDER_ID not set — skipping Google Drive sync."
fi

echo "Tool setup complete."


