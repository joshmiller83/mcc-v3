# Design brief

_Part of the agent guidance indexed in [AGENTS.md](../../AGENTS.md). Read it before working on this area._

Our site should have a clean, warm, and professional country-church aesthetic:
- **Primary Color:** Nice dark green (`#1e4d2b`).
- **Typography:**
  - **Headings & Emphasis:** `Calistoga` (a warm, friendly display serif with soft terminals).
  - **Body & default copy:** `Nunito` (a highly readable, rounded sans-serif). Leverage various weights (300 to 900) for visual hierarchy and readability.
- **Logo:** Custom logo settings (assets to be provided by the user).
- **Favicon:** `mcc_theme/favicon.svg` is the master — the logo mark (`images/logo-icon.svg`, a
  cross over an open Bible) in warm white on the brand green. `favicon.ico` beside it is rendered
  from the SVG at 16, 32 and 48px (Playwright screenshots, packed with ImageMagick `convert`),
  and `mcc_theme.settings` points at the `.ico`, because that is the one format every browser
  takes and Drupal emits a single `<link rel="icon">`. Until 2026-10-01 the site showed
  caresphere's `favicon.png`, inherited through the base theme's settings. Regenerate the `.ico`
  whenever the SVG changes.
- **Subtheming:** Style customizations are encapsulated in the custom `mcc_theme` subtheme, overriding base theme design tokens.

