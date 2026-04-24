# Plan: Add Complete XenForo CLI Command Reference to Root CLAUDE.md

## Context

The root `CLAUDE.md` (`~/Development/XenForo/2.3.9/www/CLAUDE.md`) currently has a minimal "XenForo CLI (cmd.php)" section with only 4 example commands plus a partial scaffolding list. We want to replace these two sections with a comprehensive reference of all available CLI commands from `php cmd.php list` so Claude Code can look up the correct command without guessing.

## File to Modify

`~/Development/XenForo/2.3.9/www/CLAUDE.md`

## Changes

Replace the `### XenForo CLI (cmd.php)` section (lines 29-46) and the `### XenForo Addon Scaffolding Commands` section (lines 48-67) with a single `### XenForo CLI Reference (cmd.php)` section containing:

1. **Quick-reference examples** at the top (install, rebuild, export, import — same as current, kept for convenience)
2. **Full command listing** organized by group, each with its description:
   - **Core (`xf:`)** — 16 commands: addon-disable/enable, addon-install/list/rebuild/uninstall/upgrade, convert-search-innodb, convert-utf8mb4, file-check, file-clean-up, import, import-finalize, install, rebuild-master-data, repair-db, run-jobs, style-archive-export/import, upgrade
   - **Add-on (`xf-addon:`)** — 10 commands: build-release, bump-version, create, export, install-step, sync-json, uninstall-step, upgrade-step, validate-json
   - **Development (`xf-dev:`)** — 50+ commands: all export/import variants, entity-class-properties, generate-*, class-lint, recompile-*, sync-templates, compare-schema, info, analyze-icons, etc.
   - **Scaffolding (`xf-make:`)** — 14 commands: cli-command, controller, cron, entity, extension, finder, job, listener, phrase, repository, route, service, stub-publish, template
   - **Designer (`xf-designer:`)** — 12 commands: archive-export/import, disable/enable, export/import, export-templates/style-properties, import-templates/style-properties, rebuild-metadata, revert-template, sync-templates, touch-template
   - **Rebuild (`xf-rebuild:`)** — 10 commands: attachment-optimization, attachment-thumbnails, avatar-optimization, forums, message-counts, profile-banner-optimization, reaction-score, search, sitemap, stats, threads, users

All other sections of the file remain unchanged.

## Verification

- Diff the updated file to confirm all commands from `php cmd.php list` are present
- Confirm formatting is clean and scannable
