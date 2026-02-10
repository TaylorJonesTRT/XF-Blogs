# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Add-on Overview

[TaylorJ] Blogs — a XenForo 2.3.0+ add-on (v1.5.1) providing a user blogging system. Users can create blogs, publish/schedule/draft posts, and integrate with XenForo's thread system for comments. All commands below should be run from the XenForo root (`/Users/taylorjones/Herd/xf232`).

## Common Commands

```bash
# Rebuild add-on caches after data changes
php cmd.php xf:addon-rebuild TaylorJ/Blogs

# Export development output (templates, phrases, widgets, etc.)
php cmd.php xf:dev-export --addon=TaylorJ/Blogs

# Import development data
php cmd.php xf:dev-import --addon=TaylorJ/Blogs

# Format code
php vendor/bin/php-cs-fixer fix src/addons/TaylorJ/Blogs/

# Scaffolding (interactive — prompts for add-on selection)
php cmd.php xf-make:entity
php cmd.php xf-make:controller
php cmd.php xf-make:service
php cmd.php xf-make:repository
php cmd.php xf-make:extension
php cmd.php xf-make:job
php cmd.php xf-make:listener

# Testing
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity tests/Unit/Repository tests/Unit/Utils tests/Unit/Service
```

## Architecture

### Data Model

Four database tables, all prefixed `xf_taylorj_blogs_`:

- **blog** — A user's blog container (title, description, header image, state, post count)
- **blog_post** — Individual posts within a blog (content, state, reactions, tags, attachments, scheduled time, linked discussion thread)
- **blog_watch** — Users watching a blog for notifications (composite PK: user_id + blog_id)
- **blog_post_similar** — Cached similar post IDs from XFES, rebuilt every 14 days

The add-on also extends the `xf_user` table with `taylorj_blogs_blog_count` and `taylorj_blogs_blog_post_count` columns.

### Blog Post States

Posts use a `blog_post_state` enum: `visible`, `scheduled`, `draft`, `moderated`, `deleted`. Scheduled posts are published by the `PostBlogPost` job when their time arrives.

### Key Relationships

- **Blog → BlogPosts** (one-to-many) — A blog contains many posts
- **BlogPost → Blog** (many-to-one) — Each post belongs to one blog
- **BlogPost → Discussion Thread** (one-to-one, optional) — When blog post comments are enabled, a thread is auto-created in a configured forum via `BlogPost\ThreadCreator` service
- **Blog → BlogWatch** (one-to-many) — Users watching the blog

### Controller Routing

Public routes (prefix `blogs/`):
- `Blogs` controller — Index listing all blogs, add/edit/save blog
- `Blog` controller — Single blog view, its posts, scheduled posts, watch toggle, delete
- `BlogPost` controller — Single post view, edit, delete, undelete, attachments
- `Author` controller — View a user's blogs
- `WhatsNewBlogPosts` controller — "What's New" integration

Admin route: `admin/blogs` — Blog/post management via `Admin\Controller\Blogs`.

### Service Layer

- **Blog/Create** — Creates a new blog with title, description, state, header image
- **BlogPost/Create** — Creates a post (content rendering, tags, state, scheduling, notifications, optional thread creation)
- **BlogPost/ThreadCreator** — Creates a discussion thread in the configured forum for blog post comments
- **Blog/Notify** — Sends watch notifications to blog followers via `BlogWatch` notifier

### Class Extensions (XF/ directory)

- **XF/Entity/User** — Adds `canViewBlogs()`, `canViewBlogPosts()`, `canCreateBlog()`, and permission helpers to the User entity
- **XF/ForumType/Discussion** — Registers `blogPost` as an allowed thread type while preventing users from creating it directly

### Background Jobs

- **PostBlogPost** — Publishes scheduled blog posts at their designated time (Job tests now working with framework v3.0)
- **BlogPostThreadCreation** — Creates discussion threads for blog posts
- **SimilarBlogPosts** — Rebuilds similar posts cache using XFES
- **UserBlogPostCountTotal** — Batch updates user blog post counts
- **CleanOldBlogPosts** — Periodic cleanup

### Cron Jobs

- Blog post count update (daily at midnight)
- View counter batch update (every 30 minutes) — view tracking uses a MEMORY table (`blog_post_view`) for performance, batched into the main table periodically
- Similar posts cache rebuild (hourly at :30, requires XFES)

### Utils Class

`Utils.php` provides static helpers: `getBlogPostRepo()`, `getBlogRepo()`, `log()`, `setupBlogPostThreadCreation()`, `getThreadMessage()`, and count adjustment methods. Used throughout controllers and services.

### Widgets

- **LatestBlogPosts** — Recent blog posts widget
- **OtherAuthorBlogPosts** — Random posts by the same author
- **SimilarBlogPosts** — Similar posts via XFES (removed in v1.5.1 due to server errors)

### Permissions

Two permission groups: `taylorjBlogs` (blog-level) and `taylorjBlogPost` (post-level). ~31 total permissions covering view/create/edit/delete for both users and moderators, plus tagging, inline moderation, approval bypass, and visibility of moderated/deleted content.

### Content Integration

The add-on integrates with XenForo's standard systems: Reactions, Attachments, Tags, Reports, Approval Queue, Deletion Log, Inline Moderation, Alerts, Search (via `Search/Data/Blog` and `Search/Data/BlogPost`), Find New, and Embed Resolver.

## Testing

The add-on includes a comprehensive unit test suite using PHPUnit 10+ and hampel/xenforo-test-framework v3.0. See `tests/README.md` for detailed documentation.

### Test Coverage (91 tests, 91 assertions)

- **Entity Tests (87):** Blog (46 tests), BlogPost (41 tests) - permission logic, state machines, content state management
- **Job Tests (4):** PostBlogPost job - status messages, cancellation, triggering, post publishing

### Running Tests

```bash
# All working tests (Entity + Job)
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/

# Specific suites
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Job/

# Single test file
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/BlogTest.php
```

### Test Implementation Notes

- **Entity tests:** Use `instantiateEntity()` with auto-incrementing IDs to avoid entity caching issues
- **Service/Repository tests:** Call `$this->setUpEntityManager()` in `setUp()` to enable mocking
- **Mock entities:** Use both `offsetGet()` and `get()` expectations for property access
- **JSON fields:** Pass JSON-encoded strings when instantiating entities with JSON_ARRAY columns
- **App caching:** The test suite caches the XenForo app instance to work around the single-app constraint

See `tests/README.md` for complete testing documentation and examples.

## Code Conventions

- **Indentation:** Tabs (XenForo convention), despite the repo-level PHP-CS-Fixer using 4-space config — the add-on source uses tabs
- **Class extension parent:** Always extend `XFCP_ClassName` (XenForo's class proxy pattern)
- **Entity column definition:** Use `getStructure()` with XenForo's entity structure API
- **Service pattern:** Use `ValidateAndSavableTrait` for services that validate and persist entities
- After making changes to XML data files in `_data/`, run `xf:dev-import` to load them; after making changes in the admin panel, run `xf:dev-export` to persist them
