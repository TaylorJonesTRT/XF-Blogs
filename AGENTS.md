# AGENTS.md

Operating guide for AI agents working on this add-on.

## Overview

[TaylorJ] Blogs is a XenForo 2.3.0+ add-on that gives users a blogging system: blogs, scheduled/draft/published posts, and comments through forum threads. The current version lives in `addon.json`.

The development install is `/Users/taylorjones/Development/XenForo/addondev`, a Docker environment managed by the `xf` CLI. `xf` finds the XenForo install from the current directory and forwards any command it doesn't know to `cmd.php` inside the container, so **run every command below from this add-on directory**. No wrapper script is needed.

## Before you start

- **`CONTEXT.md`**: the domain glossary. Use its terms in code, commits, and conversation.
- **`docs/adr/`**: why things are built the way they are. Read the relevant ADR before "fixing" something that looks odd. Add a new ADR (next number, short format) when you make a decision that is hard to reverse, surprising without context, and the result of a real trade-off.
- **`tests/README.md`**: test layout and examples.

## Commands

```bash
# Version bump: do this before any work on a new release or hotfix
xf xf-addon:bump-version TaylorJ/Blogs --version-id=<id> --version-string="<string>"
# ...add -f when a downgrade or hotfix realignment must bypass validation

# Rebuild add-on caches after data changes
xf xf-addon:rebuild TaylorJ/Blogs

# Load _data/ XML into the database (after editing _data/ files)
xf xf-dev:import --addon=TaylorJ/Blogs

# Persist admin-panel changes to _data/ (only for release or admin-panel persistence tasks)
xf xf-dev:export --addon=TaylorJ/Blogs

# Scaffolding (interactive; prompts for add-on selection)
xf xf-make:entity        # also: controller, service, repository, extension, job, listener

# Release build (see README.md for the post-build zip check)
xf xf-addon:build-release TaylorJ/Blogs

# Tests (run inside the container; phpunit must start from the add-on directory)
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'
xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit tests/Unit/Entity/BlogTest.php'

# Environment helpers
xf debug <xf-command>    # run a XenForo command with Xdebug
xf db                    # open the database
xf logs | xf ps | xf up | xf down
```

No code formatter is set up in this environment. Match the surrounding code by hand (see Code Conventions).

## Release Versioning Workflow

- Bump the version before any implementation work on a new release or hotfix.
- Change the version only with `xf xf-addon:bump-version`. Never hand-edit the version fields in `addon.json`.
- Use `-f` when a downgrade or hotfix realignment needs to bypass version validation.
- Build releases only with `xf xf-addon:build-release`, never by zipping the folder. `build.json` strips dev-only files. See [ADR 0006](docs/adr/0006-releases-via-xenforo-builder.md).

## Code Map

| Path | Responsibility |
|---|---|
| `Entity/` | `Blog`, `BlogPost`, `BlogWatch`, `BlogPostSimilar`: structure, permission checks, state transitions |
| `Finder/`, `Repository/` | Query building and data access (counts, view batching, similar-post cache) |
| `Service/Blog/`, `Service/BlogPost/` | Create / Edit / Delete / Approve, `Blog\Notify`, `BlogPost\ThreadCreator` |
| `Pub/Controller/` | Public `blogs/` routes: `Blogs` (index, add), `Blog`, `BlogPost`, `Author`, `WhatsNewBlogPosts` |
| `Admin/Controller/` | `admin/blogs` management |
| `Job/`, `Cron/` | Scheduled publishing, thread creation, count rebuilds, view batching, similar-post rebuilds, cleanup |
| `ThreadType/`, `XF/ForumType/` | The `blogPost` thread type and the Discussion forum type extension |
| `XF/Entity/User.php`, `Listener.php` | User permission helpers, the extra `xf_user` count columns, templater and member-stat hooks |
| `Widget/` | Latest posts, other posts by the same author, similar posts |
| Content handlers | `Alert/`, `ApprovalQueue/`, `Attachment/`, `EmbedResolver/`, `FindNew/`, `InlineMod/`, `Reaction/`, `Report/`, `Search/`, `Tag/` |
| `Utils.php` | Static helpers used across controllers and services (repo getters, logging, count adjustment, thread setup) |
| `_data/` | XenForo dev output (templates, phrases, options, permissions, etc.) |
| `Setup.php` | Install, upgrade, and uninstall steps |

## Code Conventions

- **Indentation:** tabs (XenForo convention).
- **Class extensions:** always extend `XFCP_ClassName`.
- **Entities:** define columns and relations in `getStructure()`.
- **Services:** use `ValidateAndSavableTrait` for services that validate and persist entities.
- **Dev data:** after editing XML in `_data/`, run `xf xf-dev:import`. After changing anything in the admin panel, run `xf xf-dev:export` to persist it.

## Testing Notes

PHPUnit 12 with `hampel/xenforo-test-framework` v5 (`^5.41`). See `tests/README.md` for the full guide.

- **App instance:** every test boots its own isolated XenForo app with only this add-on loaded. See [ADR 0009](docs/adr/0009-tests-boot-isolated-app-per-test.md).
- **Strict mode:** `phpunit.xml` fails the run on any deprecation, notice, warning, or risky test.
- **Entity tests:** use `instantiateEntity()`. The auto-incrementing ID counters stay, though a fresh app per test no longer needs them.
- **Service/Repository tests:** `mockEntity()`, `mockRepository()`, `mockFinder()`, and `mockService()` work without a `setUp()` of your own.
- **Visitor:** `mockVisitor($permissions, $userId)` builds a real, read-only User via `actingAsMember()`. Pass extra columns to `actingAsMember()` instead of assigning them afterwards.
- **Mock entities:** set expectations on both `offsetGet()` and `get()` for property access.
- **JSON columns:** pass JSON-encoded strings when instantiating entities with `JSON_ARRAY` columns.
