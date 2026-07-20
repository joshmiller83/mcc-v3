# Implementation Plan: Discovery, Architecture, and Migration Strategy

This plan establishes a discovery phase to analyze the existing website structure, simplify the database architecture with user input, and define a repeatable, reversible migration pipeline.

---

## Goal: Architecture Discovery & Migration Setup
We want to extract the structure of the existing site, simplify the taxonomy and content model, design the clean new Drupal CMS target structure, and build a migration script that is easily testable and reversible. 

We also ensure our AI assistant CLI tools (`agy` and `claude`) are automatically provisioned and ready for terminal use as soon as the Codespace starts.

---

## User Review Required
> [!IMPORTANT]
> - **Codespace Setup**: Both `agy` and `claude` CLI commands are now configured to be automatically installed and symlinked into the system PATH via `.devcontainer/setup.sh`.
> - **Authentication**:
>   - You will need to run `terminus auth:login --machine-token=<YOUR_MACHINE_TOKEN>` once the Codespace starts.
>   - You will need to log in to `agy` (via Google Auth) or `claude` (via Anthropic Auth) on their respective first runs inside the terminal.
> - **Interactive review**: We will hold an interactive review of the content model (Nodes, Fields, Taxonomies, Files) after Phase 1 before any migration code is written.

---

## Open Questions
> [!IMPORTANT]
> 1. What version of Drupal is the current/old website running? (e.g., Drupal 7, Drupal 8/9/10). This determines whether we use standard core Drupal-to-Drupal migrations or custom source plugins.
> 2. Are there any known custom content types or fields on the old site that are no longer in use or should definitely be retired/merged?

---

## Proposed Changes

### Phase 1: Architecture & Discovery (Pre-Migration Analysis)
*Analyze the existing site structure without making any configuration modifications.*

* **Step 1.1**: Authenticate Terminus within the Codespace.
* **Step 1.2**: Execute remote Drush queries against the Pantheon environment to catalog:
  - Content bundles (node types) and item counts.
  - Vocabulary lists (taxonomies) and term counts.
  - Custom module usage.
  - Active fields per content type.
* **Step 1.3**: Generate a structural analysis document listing all existing content types, taxonomies, and their fields.

---

### Phase 2: Simplification & Mapping (Interactive Phase)
*Align on the target content structure before writing migrations.*

* **Step 2.1**: Interactive review of the discovery document. Discuss:
  - Which content types are obsolete (e.g., old events, archive types).
  - Which fields can be combined (e.g., reducing multiple text fields to single body/formatted fields).
  - Taxonomy simplification (e.g., tag consolidation).
* **Step 2.2**: Draft the new site structure map showing `[Old Content Type/Field] -> [New Content Type/Field]`.
* **Step 2.3**: Get your explicit sign-off on the target structure.

---

### Phase 4: Target Site Setup & Configuration
*Construct the target structure in the repo.*

* **Step 3.1**: Initialize the new Drupal CMS project layout using composer.
* **Step 3.2**: Configure the new content types and fields in code/config (utilizing Drupal core features).
* **Step 3.3**: Export configuration to `/config/sync` so the structure is tracked in git.

---

### Phase 4: Migration Development & Reset Strategy
*Build the migration with rollback and database reset workflows to handle complexity.*

* **Step 4.1**: Create a custom migration module `mcc_migration` containing the mapping YAMLs.
* **Step 4.2**: Configure local DDEV to host a copy of the source database as a second database connection (`migrate`).
* **Step 4.3**: Provide clear commands to reset both the migrated content and target schema:
  - To roll back imported data: `ddev drush migrate:rollback`
  - To clean-reset the entire site configuration/database: DDEV database import scripts.

---

## Verification Plan

### Manual Verification
1. **Terminus Access**: Run a status check against Pantheon to confirm we can read metadata.
2. **Structural Mapping Review**: Compare the generated target schema against the live site.
3. **Migration Verification & Rollback**:
   - Run `ddev drush migrate:import --group=mcc` and verify the node/entity counts.
   - Run `ddev drush migrate:rollback --group=mcc` and verify that all imported entities are cleanly deleted without leaving orphaned references.
