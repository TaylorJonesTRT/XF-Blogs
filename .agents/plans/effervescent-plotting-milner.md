# Bring TaylorJ/Blogs Up to XenForo Standards

## Context

Audit of the Blogs addon against XenForo resource manager standards and core codebase conventions. The addon is functional but has several logic bugs, standards violations, dead code, and schema issues that should be addressed before release.

---

## Critical Fixes (Broken Logic)

### 1. Fix `canEdit()` inverted permission logic
**File:** `Entity/Blog.php:74-84`

The `else` branch (non-owner editing) returns `false` regardless of whether the user has `canEditAny`. Both branches return false, making moderator editing impossible.

**Fix:** Return `true` when `canEditAny` is granted, `false` otherwise.

### 2. Fix `canView()` overly restrictive logic
**File:** `Entity/Blog.php:50-60`

Uses `!viewOwn || !viewAny` — requires BOTH permissions. Fix to owner-based check: blog owner needs `viewOwn`, everyone else needs `viewAny` (standard XF own/any pattern).

### 3. Admin controller uses array access on ParameterBag
**File:** `Admin/Controller/Blogs.php:17`

`$params['blog_id']` should be `$params->blog_id`. ParameterBag is an object.

### 4. Admin controller `actionBlogDelete()` calls non-existent plugin
**File:** `Admin/Controller/Blogs.php:76`

`getBlogsPlugin()` references `TaylorJ\Blogs:Blog` controller plugin which doesn't exist. The commented-out code above shows the intended implementation — uncomment and fix it, or implement the plugin.

---

## High-Priority Standards Violations

### 5. Raw SQL to read an option value
**File:** `XF/ForumType/Discussion.php:29-34`

Queries `xf_option` table directly. Replace with `\XF::options()->taylorjBlogsBlogPostForum`.

### 6. Missing primary key on `blog_watch` table
**File:** `Setup.php:101-108`

Table has no primary key defined. Add `$table->addPrimaryKey(['user_id', 'blog_id'])`. This also makes the `DuplicateKeyException` catch in `Repository/BlogWatch.php` unnecessary.

### 7. `blog_post_title` column size mismatch
- `Setup.php:56` creates the column as `varchar(50)`
- `Entity/BlogPost.php:563` defines `maxLength => 300`

Entity allows 300 chars but DB truncates at 50. Add an upgrade step to `ALTER` the column to `varchar(300)`.

### 8. Copy-paste remnant: `resource_category` references
**File:** `XF/Entity/User.php:24-38`

`hasBlogPostPermission()` and `cacheResourceCategoryPermissions()` reference `resource_category` content type — copied from XFRM. Convert these to use blog-specific content type identifiers (`taylorj_blogs_blog` / `taylorj_blogs_blog_post`).

---

## Medium-Priority Code Quality

### 9. Dead/broken `getTotalBlogPosts()` method
**File:** `Entity/Blog.php:262-268`

References non-existent entity `TaylorJ\Blogs:Post`, uses `$this->id` instead of `$this->blog_id`, variable named `$test`, returns an incomplete Finder without calling `total()`. Remove or fix.

### 10. Unused `deleteBlogHeaderImageFiles()` with missing parens
**File:** `Service/Blog/Edit.php:66-72`

Calls `$this->blog->getBlogHeaderImage` (missing `()`). Method is never called. Remove or integrate into the edit flow.

### 11. Duplicate filter calls in controllers
- `Pub/Controller/Blog.php:148-149` and `164-167` — same filters requested twice
- `Pub/Controller/BlogPost.php:158` and `164` — same filters requested twice

Consolidate to single filter call.

### 12. Direct Finder instead of `assertBlogExists()`
**File:** `Pub/Controller/Blog.php:136`

Uses manual `finder()->where()->fetchOne()` instead of the existing `assertBlogExists()` helper, which provides proper 404 handling.

### 13. `blogMadeVisible()` relation check
**File:** `Entity/Blog.php:330-340`

`if ($this->BlogPosts)` is always truthy for a relation. Should check `$this->BlogPosts->count()` to avoid iterating an empty collection.

### 14. Typo in phrase
**File:** `_data/phrases.xml` — "vendofr" should be "vendor" in `taylorj_blogs_vendor_folder_missing`.

### 15. Duplicate widget definitions
**File:** `_data/widget_definitions.xml` — `taylorj_similar_posts` and `taylorj_blo_similar_posts` both point to the same class. Remove the duplicate.

### 16. Admin controller missing permission checks
**File:** `Admin/Controller/Blogs.php`

No `assertAdminPermission()` calls. Should verify admin has permission to manage blogs.

---

## Files to Modify

| File | Changes |
|------|---------|
| `Entity/Blog.php` | Fix `canView()`, `canEdit()`, remove `getTotalBlogPosts()`, fix `blogMadeVisible()` |
| `Admin/Controller/Blogs.php` | Fix ParameterBag access, fix delete action, add admin permission check |
| `XF/ForumType/Discussion.php` | Replace raw SQL with `\XF::options()` |
| `XF/Entity/User.php` | Fix or remove `resource_category` references |
| `Setup.php` | Add PK to blog_watch, add upgrade step for blog_post_title column size |
| `Service/Blog/Edit.php` | Remove or fix `deleteBlogHeaderImageFiles()` |
| `Pub/Controller/Blog.php` | Fix duplicate filters, use `assertBlogExists()` |
| `Pub/Controller/BlogPost.php` | Fix duplicate filters |
| `_data/phrases.xml` | Fix "vendofr" typo |
| `_data/widget_definitions.xml` | Remove duplicate definition |

---

## Verification

1. Run existing tests: `cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs && XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/`
2. Rebuild addon: `php cmd.php xf:addon-rebuild TaylorJ/Blogs` (from XF root)
3. Export dev output: `php cmd.php xf:dev-export --addon=TaylorJ/Blogs`
4. Manual smoke test: create a blog, edit it as owner and as admin, verify permissions work correctly
5. Verify blog post titles can be longer than 50 characters after the upgrade step
