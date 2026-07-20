# Mechanicsburg Christian Church — Website Rebuild

This repo is the successor to [mechanicsburgchristian.com](https://mechanicsburgchristian.com/). It is built on a fresh install of **Drupal CMS**, designed to be simple to maintain and easy to keep updated for years to come.

Mechanicsburg Christian Church is a small country church. We read the Bible, we believe Jesus made a way for all sinners to repent and join him in his kingdom, and we love people unconditionally. This website exists to serve that mission — nothing more complicated than that.

## About Drupal CMS
Drupal CMS is an open source product that enables site builders to easily create new Drupal sites and extend them with smart defaults, all using their browser.

## Stack

- **CMS:** Drupal CMS (latest), fresh install — not a migration of the old site's codebase
- **Local dev:** [DDEV](https://ddev.com/)
- **Hosting:** [Pantheon](https://pantheon.io/)
- **Deploys:** push to GitHub → Pantheon build process picks it up (integration TBD)

## Getting started

Development primarily happens in **GitHub Codespaces** (see [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md)). Open this repo in a Codespace — `.devcontainer/devcontainer.json` installs Docker-in-Docker and DDEV automatically via DDEV's official [`install-ddev`](https://github.com/ddev/ddev/tree/main/containers/devcontainers/install-ddev) devcontainer feature.

Once the codespace is up:

```bash
ddev start
ddev composer install
ddev drush si   # or drush updb / cim, depending on where the site is
```

Site should then be reachable at the URL `ddev` prints out (`ddev launch` also works).

### Run Locally (Outside Codespaces)
If you want to use [DDEV](https://ddev.com) to run Drupal CMS locally on your host machine, follow these instructions:

1. Install DDEV following the [documentation](https://ddev.com/get-started/)
2. Open the command line and `cd` to the root directory of this project
3. Run `ddev launch`

Drupal CMS has the same system requirements as Drupal core, so you can use your preferred setup to run it locally. [See the Drupal User Guide for more information](https://www.drupal.org/docs/user_guide/en/installation-chapter.html) on how to set up Drupal.

### Installation options

The Drupal CMS installer offers a list of features preconfigured with smart defaults. You will be able to customize whatever you choose, and add additional features, once you are logged in.

After the installer is complete, you will land on the dashboard.

**Codespaces prebuilds:** not yet enabled. This can only be configured through the repo's Settings → Codespaces UI (not as code), and it consumes Codespaces storage quota, so it's an opt-in decision rather than something set up automatically.

## Environments

Terminus gives access to Pantheon's `dev`, `test`, and `live` environments via `ddev terminus`. See [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md) for rules on when/how those commands should be used.

## Ground rules

- Keep it clear and straightforward. This is a small church site, not an enterprise platform — prefer boring, well-supported Drupal patterns over clever ones.
- Favor core and well-maintained contrib modules over custom code. Custom code should be the exception, and only when there's no reasonable alternative.
- Keep the site upgradable. Don't fight Drupal CMS's defaults without a good reason.
- Document any non-obvious decision in the commit message or a code comment — future maintainers (human or AI) won't have this conversation's context.

See [AGENTS.md](file:///workspaces/mcc-v3/AGENTS.md) for rules specific to AI coding assistants working in this repo.

## Documentation

* [Drupal CMS User Guide](https://project.pages.drupalcode.org/drupal_cms/)
* Learn more about managing a Drupal-based application in the [Drupal User Guide](https://www.drupal.org/docs/user_guide/en/index.html).

## Contributing & Support

[Report issues in the queue](https://drupal.org/node/add/project-issue/drupal_cms), providing as much detail as you can. You can also join the #drupal-cms-support channel in the [Drupal Slack community](https://www.drupal.org/slack).

Drupal CMS is developed in [a separate repository on Drupal.org](https://www.drupal.org/project/drupal_cms). See [CONTRIBUTING.md](CONTRIBUTING.md) for more information.

## License

Drupal CMS and all derivative works are licensed under the [GNU General Public License, version 2 or later](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

Learn about the [Drupal trademark and logo policy here](https://www.drupal.com/trademark).
