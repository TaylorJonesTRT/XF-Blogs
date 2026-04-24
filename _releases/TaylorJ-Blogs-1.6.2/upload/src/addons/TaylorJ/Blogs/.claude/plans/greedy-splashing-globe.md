# Plan: Move Blogs Addon to New Docker Dev Environment

## Context

Moving the `TaylorJ/Blogs` addon from the old Laravel Herd environment (`~/Herd/xf232/`) to a new Docker-based XenForo 2.3.9 environment (`~/Development/XenForo/2.3.9/www/`). Everything must be preserved — `.git`, `.claude`, `.idea`, `.phpunit.cache`, `vendor/`, `_releases/`, etc. The CLAUDE.md files also need updating for the new paths and Docker-based workflow.

## Steps

### 1. Copy the addon directory

```bash
mkdir -p ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ
cp -a ~/Herd/xf232/src/addons/TaylorJ/Blogs ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs
```

`cp -a` preserves all hidden files/folders, permissions, and timestamps.

### 2. Copy parent CLAUDE.md files

```bash
# Root-level CLAUDE.md (project-wide guidance)
cp ~/Herd/xf232/CLAUDE.md ~/Development/XenForo/2.3.9/www/CLAUDE.md

# TaylorJ vendor-level CLAUDE.md (add-on standards)
cp ~/Herd/xf232/src/addons/TaylorJ/CLAUDE.md ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/CLAUDE.md
```

### 3. Update paths in addon's `CLAUDE.md`

**File:** `~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs/CLAUDE.md`

Replace all `/Users/taylorjones/Herd/xf232` → `/Users/taylorjones/Development/XenForo/2.3.9/www`

### 4. Update paths in `tests/README.md`

**File:** `~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs/tests/README.md`

Same path replacement as step 3.

### 5. Update root `CLAUDE.md` for Docker environment

**File:** `~/Development/XenForo/2.3.9/www/CLAUDE.md`

Changes needed:
- Version: `2.3.7` → `2.3.9`
- Environment: `running on MySQL via Laravel Herd` → `running on MariaDB via Docker`
- Database: `MySQL 127.0.0.1:3306, db: xf232php82` → `MariaDB 127.0.0.1:3307, db: xenforo` (user: `xenforo`, pw: `xenforo`)
- Wrap all `php cmd.php` commands with `docker compose exec php` prefix
- Wrap `php vendor/bin/php-cs-fixer` and `php phpcs.phar` commands similarly
- Wrap `composer install` similarly
- Note the XenForo root on host is `~/Development/XenForo/2.3.9/www`

### 6. Update addon `CLAUDE.md` commands for Docker

**File:** `~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs/CLAUDE.md`

Wrap XenForo CLI commands (`php cmd.php ...`) with `docker compose exec php` prefix. The testing commands (`./vendor/bin/phpunit`) run on the host and don't need Docker wrapping.

### 7. Install composer dependencies

```bash
cd ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs
composer install
```

### 8. Verify the setup

- Git works: `git -C ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs log --oneline -5`
- `.claude` intact: `ls -la ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs/.claude/`
- Tests pass: `cd ~/Development/XenForo/2.3.9/www/src/addons/TaylorJ/Blogs && XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/`
- Import addon data: `cd ~/Development/XenForo/2.3.9/www && docker compose exec php php cmd.php xf:dev-import --addon=TaylorJ/Blogs`

## Files Modified

| File | Change |
|------|--------|
| `Blogs/CLAUDE.md` | Update paths + Docker command prefixes |
| `Blogs/tests/README.md` | Update paths |
| `www/CLAUDE.md` (new, copied) | Update for XF 2.3.9, Docker, MariaDB |
| `TaylorJ/CLAUDE.md` (new, copied) | No changes needed (standards doc) |
