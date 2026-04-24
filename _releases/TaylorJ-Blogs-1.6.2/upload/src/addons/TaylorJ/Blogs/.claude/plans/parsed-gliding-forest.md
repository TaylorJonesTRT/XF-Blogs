# Hotfix: Blog Post Title Length Column Missing From v1.6.x

## Context

In v1.6.0, the configurable blog post title length option (`taylorjBlogsBlogPostTitleLength`) was added along with entity-level validation (`maxLength: 300`, `verifyBlogPostTitle()`), but the database column was never altered from `varchar(50)` to `varchar(300)`. No upgrade method was added, and `installStep2()` wasn't updated either. This means all users on v1.6.0 or v1.6.1 have a `varchar(50)` column while the admin setting defaults to 200 — the setting has no effect because MySQL truncates/errors at 50 characters.

The v1.7.0 refactor branch already has the fix (`upgrade1060270Step1`), but we also need a v1.6.2 hotfix on `main` for users who can't wait.

## Plan

### Step 1: Create hotfix branch from main

Create a `hotfix/1.6.2-title-length` branch from `main` (currently at v1.6.1, commit `b4a89d2`).

### Step 2: Update `Setup.php` on hotfix branch

**File:** `Setup.php`

Add two upgrade methods (identical to what's already in the v1.7.0 branch):

```php
public function upgrade1060270Step1()
{
    $this->alterTable('xf_taylorj_blogs_blog_post', function (Alter $table)
    {
        $table->changeColumn('blog_post_title', 'varchar', 300);
    });
}

public function upgrade1060270Step2()
{
    $this->alterTable('xf_taylorj_blogs_blog_watch', function (Alter $table)
    {
        $table->addPrimaryKey(['user_id', 'blog_id']);
    });
}
```

Also update `installStep2()` to create the column as `varchar(300)` instead of `varchar(50)` (for fresh installs).

### Step 3: Bump version using XenForo CLI

Use the XenForo CLI to bump the version properly:

```bash
../../../../scripts/run_in_container.sh php cmd.php xf-addon:bump-version TaylorJ/Blogs --version-string 1.6.2 --version-id 1060270
```

This updates `addon.json` to version `1.6.2` / `1060270`, matching the upgrade method naming so XenForo will run it for anyone on ≤1.6.1.

### Step 4: Export add-on data

```bash
../../../../scripts/run_in_container.sh php cmd.php xf-dev:export --addon=TaylorJ/Blogs
```

### Step 5: Commit and merge hotfix to main

Commit the changes and merge the hotfix branch into `main`.

### Step 6: Verify v1.7.0 branch compatibility

The `upgrade1060270Step1` method already exists on `feature/claudeRefactor`. For users who apply the 1.6.2 hotfix and later upgrade to 1.7.0:
- `changeColumn('blog_post_title', 'varchar', 300)` is idempotent — running it again on an already-varchar(300) column is a harmless no-op
- `addPrimaryKey(['user_id', 'blog_id'])` — XenForo's schema manager handles this gracefully if the PK already exists

No changes needed on the v1.7.0 branch.

## Files to Modify (on hotfix branch)

| File | Change |
|---|---|
| `Setup.php` | Add `upgrade1060270Step1()` and `upgrade1060270Step2()`; update `installStep2()` column to `varchar(300)` |
| `addon.json` | Bumped via `xf-addon:bump-version` CLI command |

## Verification

1. On hotfix branch, run `../../../../scripts/run_in_container.sh php cmd.php xf-addon:upgrade TaylorJ/Blogs` against a database at v1.6.1 state and confirm `blog_post_title` column is altered to `varchar(300)`
2. Confirm the admin title length setting takes effect after upgrade
3. Run existing tests: `XENFORO_HOST=1 XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/`
