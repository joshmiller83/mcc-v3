# Mechanicsburg Christian Church — Website Rebuild

This repo is the successor to [mechanicsburgchristian.com](https://mechanicsburgchristian.com/). It's a fresh Drupal CMS site, built to be simple to maintain and easy to keep updated for years to come.

Mechanicsburg Christian Church is a small country church. We read the Bible, we believe Jesus made a way for all sinners to repent and join him in his kingdom, and we love people unconditionally. This website exists to serve that mission — nothing more complicated than that.

## Stack

- **CMS:** Drupal CMS (latest), fresh install — not a migration of the old site's codebase
- **Local dev:** [DDEV](https://ddev.com/)
- **Hosting:** [Pantheon](https://pantheon.io/)
- **Deploys:** push to GitHub → Pantheon build process picks it up (integration TBD)

## Getting started

Development happens in **GitHub Codespaces**, not on a local machine (see `AGENTS.md`). Open this repo in a Codespace — `.devcontainer/devcontainer.json` installs Docker-in-Docker and DDEV automatically via DDEV's official [`install-ddev`](https://github.com/ddev/ddev/tree/main/containers/devcontainers/install-ddev) devcontainer feature.

Once the codespace is up:

```bash
ddev start
ddev composer install
ddev drush si   # or drush updb / cim, depending on where the site is
```

Site should then be reachable at the URL `ddev` prints out (`ddev launch` also works).

**Codespaces prebuilds:** not yet enabled. This can only be configured through the repo's Settings → Codespaces UI (not as code), and it consumes Codespaces storage quota, so it's an opt-in decision rather than something set up automatically.

## Environments

Terminus gives access to Pantheon's `dev`, `test`, and `live` environments via `ddev terminus`. See `AGENTS.md` for rules on when/how those commands should be used.

## Ground rules

- Keep it clear and straightforward. This is a small church site, not an enterprise platform — prefer boring, well-supported Drupal patterns over clever ones.
- Favor core and well-maintained contrib modules over custom code. Custom code should be the exception, and only when there's no reasonable alternative.
- Keep the site upgradable. Don't fight Drupal CMS's defaults without a good reason.
- Document any non-obvious decision in the commit message or a code comment — future maintainers (human or AI) won't have this conversation's context.

See `AGENTS.md` for rules specific to AI coding assistants working in this repo.
