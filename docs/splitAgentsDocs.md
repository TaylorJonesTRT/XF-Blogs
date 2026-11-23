# Plan: Slim AGENTS.md, switch to `xf` CLI, split knowledge into CONTEXT.md + ADRs

## Context

AGENTS.md is out of date. It describes the old `2.3.9/www` setup, where every command went through `../scripts/run_in_container.sh` and tests ran on the host with `XENFORO_HOST=1` against `127.0.0.1:3307`. The add-on now lives in `/Users/taylorjones/Development/XenForo/addondev` (XF 2.3.13, Docker), which is managed by the `xf` CLI (`/opt/homebrew/bin/xf`). I checked how that setup behaves:

- `xf` finds the XF install from the current directory and forwards unknown commands to `cmd.php`. So `xf xf-dev:import` works when run directly from the add-on directory (tested with `xf list`).
- `xf php` and `xf exec xf …` run in `/var/www/html` (the XF root). The test bootstrap uses `rootDir = '../../../..'`, so phpunit has to start from the add-on directory: `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'` (tested: the path resolves and `vendor/bin/phpunit` exists).
- MySQL no longer exposes a host port, so running tests on the host no longer works.
- The new environment has no php-cs-fixer. **Decision: drop the "Format code" command.**

AGENTS.md also mixes domain knowledge and design rationale into the operating instructions, and some of it is stale (it says v1.5.1 while addon.json says 1.7.5; it gives two different test counts, 91 and 253). That knowledge moves out:
- **Glossary → `CONTEXT.md` at the add-on root** (decided). The user's `grill-with-docs` and `improve-codebase-architecture` skills already read it.
- **Decisions → `docs/adr/NNNN-slug.md`**, using the short ADR format those skills define (title plus 1–3 sentences; optional Status/Consequences sections).

Step 0: copy this plan to `docs/splitAgentsDocs.md`, per the user's global CLAUDE.md.

## Changes

### 1. `AGENTS.md`: rewrite as a short operating guide
- **Overview:** one paragraph. Point to `addon.json` for the version instead of hardcoding it. Give the environment root (`addondev`, Docker, managed by `xf`) and say all commands run from the add-on directory.
- **Commands:** replace every `cd …/2.3.9/www && ../scripts/run_in_container.sh php cmd.php X` with `xf X`:
  - `xf xf-addon:bump-version TaylorJ/Blogs --version-id=… --version-string="…"` (add `-f` for downgrade/hotfix)
  - `xf xf-addon:rebuild TaylorJ/Blogs`
  - `xf xf-dev:import --addon=TaylorJ/Blogs` / `xf xf-dev:export --addon=TaylorJ/Blogs` (export only for release/admin-panel persistence tasks, as now)
  - `xf xf-make:entity|controller|service|repository|extension|job|listener`
  - `xf xf-addon:build-release TaylorJ/Blogs`, then the zip check (see §4)
  - Helpers: `xf debug <cmd>` (Xdebug), `xf db`, `xf logs`, `xf ps`, `xf up`/`xf down`
  - Tests: `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit [path]'`. Drop `XENFORO_HOST`. `XDEBUG_MODE` already defaults to off in `compose.development.yaml`.
  - Remove "Format code".
- **Release versioning workflow:** keep the imperative rules (bump before any work; only `bump-version`; never hand-edit `addon.json`; `-f` for realignment), rewritten for `xf`. Link to the ADR.
- **Code map:** a compact directory → responsibility list (Entity, Finder, Repository, Service, Pub/Admin Controller, Job, Cron, Widget, ThreadType, XF/ extensions, Utils, `_data`). Drop the long prose, which moves to CONTEXT.md and ADRs or can be read from the code.
- **Code conventions:** keep tabs, `XFCP_` parent, `getStructure()`, `ValidateAndSavableTrait`, and the `_data` import/export rule. Drop the clause about the repo-level PHP-CS-Fixer, which no longer exists.
- **Testing:** keep the implementation notes (`instantiateEntity()` with incrementing IDs, `setUpEntityManager()`, mocking both `offsetGet`/`get`, JSON-encoded strings for JSON columns). Drop the stale test counts and link to `tests/README.md`.
- **"Before you start" pointers:** read `CONTEXT.md` for terms and `docs/adr/` for why things are built the way they are; add an ADR when making a hard-to-reverse, surprising trade-off.

### 2. `CONTEXT.md` (new): glossary, in the grill-with-docs format
Title plus one-line description, then **Language** grouped under subheadings, **Relationships**, an **Example dialogue**, and **Flagged ambiguities**. Check each definition against the code while writing.
- *Blogs:* **Blog**, **Author** (blog owner), **Header image**, **Blog limit**, **Blog Watch** / **Watcher**
- *Posts:* **Blog Post**, **Blog Post State** (`visible`, `scheduled`, `draft`, `moderated`, `deleted`), **Scheduled post**, **Draft**, **Soft delete** vs **Hard delete**, **Read time**
- *Comments:* **Discussion Thread** (avoid "comment thread", "blog thread"), **Comment forum** (the `taylorjBlogsBlogPostForum` option), **`blogPost` thread type**, **Deletion thread action**
- *Derived data:* **Blog post count** / **Blog count** (denormalized on `xf_user` and blog), **View count**, **Similar blog posts**
- *Flagged ambiguities:*
  - "comments" means posts in the Discussion Thread; there is no native comment entity.
  - "blogs" (the route/index) vs "userblogs" (legacy routes, removed in 1.7.0).
  - "delete" means a soft delete unless stated otherwise.
  - The Similar Blog Posts widget is described as "removed in v1.5.1", but `_data/widget_definitions.xml` still registers `taylorj_blo_similar_posts`. Record what's actually true.

### 3. `docs/adr/` (new): one short ADR per real decision
| # | File | Decision (source) |
|---|---|---|
| 0001 | `0001-comments-via-forum-threads.md` | Comments are XF threads in a configured forum, using the custom `blogPost` thread type. The `Discussion` forum type is extended so it allows the type but users can't create it directly. (`ThreadType/BlogPostHandler.php`, `XF/ForumType/Discussion.php`, `Service/BlogPost/ThreadCreator.php`) |
| 0002 | `0002-scheduled-posts-via-deferred-jobs.md` | Each scheduled post gets its own `enqueueLater` job with id `taylorjblogs_scheduledpost_{id}`. The job re-checks the post date, so no polling cron is needed. (`Service/BlogPost/Create.php::insertJob`, `Job/PostBlogPost.php`) |
| 0003 | `0003-view-counts-buffered-in-memory-table.md` | Views go into a MEMORY-engine table, and a cron writes them to the main table every 30 minutes (the same pattern as XF thread views). Losing unflushed counts on a DB restart is accepted. (`Setup.php`, `Repository/BlogPost.php`, `Cron/BlogPost.php`) |
| 0004 | `0004-similar-posts-cached-from-xfes.md` | Similar posts are fetched from XFES and cached in `blog_post_similar`, then rebuilt by an hourly job with about 14-day staleness. XFES is optional. Status records the widget's actual state. (`Job/SimilarBlogPosts.php`, `Cron/SimilarBlogPosts.php`) |
| 0005 | `0005-denormalized-blog-counters.md` | Blog and post counts are stored on `xf_user` and the blog, and a daily cron or rebuild job corrects drift. (`Listener.php`, `Cron/Blog.php`, `Job/UserBlogPostCountTotal.php`) |
| 0006 | `0006-releases-via-xenforo-builder.md` | Releases are built only with `xf-addon:build-release` plus `build.json`, which strips dev files; never zip the folder by hand. Versions change only through `bump-version`. (`build.json`, README) |
| 0007 | `0007-tests-cache-xenforo-app.md` | XF allows only one app instance, so tests cache it and inject it again through reflection. (`tests/CreatesApplication.php`) |
| 0008 | `0008-docker-dev-env-via-xf-cli.md` | Development runs in the `xf`-managed Docker environment, and tests run inside the container. Replaces `run_in_container.sh` and host-side `XENFORO_HOST` testing. |

### 4. Keep the new docs out of release zips
- `build.json`: add `docs` and `CONTEXT.md` to the existing `rm` lines in `exec`.
- `README.md` "Release Build": replace the `run_in_container` command with `xf xf-addon:build-release TaylorJ/Blogs`, and add `docs/` and `CONTEXT.md` to the `zipinfo | grep -E` check.

### 5. `tests/README.md`: same command cleanup
Replace the `2.3.9/www` path and `XENFORO_HOST=1 … ./vendor/bin/phpunit` with the `xf exec` form. Make the test counts consistent, either by updating them from a real run or by removing them.

## Verification
1. `grep -rn "run_in_container\|2\.3\.9\|XENFORO_HOST\|3307" AGENTS.md README.md tests/README.md CONTEXT.md docs/` returns nothing.
2. Run `xf list xf-addon` and `xf list xf-make` from the add-on directory to confirm the forwarded commands exist.
3. Run the documented test command, `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'`, and report pass/fail honestly. Use the real counts in tests/README.md.
4. Check that every link in AGENTS.md (`CONTEXT.md`, `docs/adr/*`, `tests/README.md`) resolves.
5. Optional, and only after asking first because it writes a zip to `_releases/`: run `xf xf-addon:build-release TaylorJ/Blogs` plus the README zipinfo check, to confirm `docs/` and `CONTEXT.md` are excluded.
