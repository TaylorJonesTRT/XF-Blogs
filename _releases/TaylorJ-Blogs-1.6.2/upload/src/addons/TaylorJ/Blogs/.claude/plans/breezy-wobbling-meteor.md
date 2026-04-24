# Fix TaylorJ/Blogs Standards Violations & Bugs

## Context

Audit of the Blogs addon revealed broken permission logic, XenForo standards violations, dead code, and schema issues. This plan addresses all verified issues, organized by severity. Every item below has been verified against the actual codebase.

---

## 1. Critical — Broken Logic

### 1a. Fix `canEdit()` inverted permission logic
**File:** `Entity/Blog.php:74-85`
The else branch (non-owner) returns `false` even when `canEditAny` is granted. Both code paths return false, making moderator editing impossible.
**Fix:** Return `true` when `canEditAny` is granted.

### 1b. Fix `canView()` overly restrictive logic
**File:** `Entity/Blog.php:50-60`
Uses `!viewOwn || !viewAny` — requires BOTH permissions. XF pattern is owner-based: blog owner needs `viewOwn`, everyone else needs `viewAny`.
**Fix:** Check ownership, then apply the appropriate single permission.

### 1c. Admin controller `actionBlogDelete()` calls non-existent plugin
**File:** `Admin/Controller/Blogs.php:76-82`
`getBlogsPlugin()` returns `$this->plugin('TaylorJ\Blogs:Blog')` but no `ControllerPlugin/Blog.php` exists — this crashes at runtime.
**Fix:** Uncomment the working delete logic above (lines 62-74) using XF's built-in `XF:Delete` plugin, remove the broken plugin call.

### 1d. Admin controller uses array access on ParameterBag
**File:** `Admin/Controller/Blogs.php:17`
`$params['blog_id']` — ParameterBag uses property access `$params->blog_id`.
**Fix:** Change to `$params->blog_id`.

### 1e. Resolve `Service/BlogPost/Create.php` merge conflict
**File:** `Service/BlogPost/Create.php` (git status: `UU`)
Unresolved merge state. Must be resolved and committed cleanly.
**Fix:** Verify the working tree version is correct, stage it.

---

## 2. High — Standards Violations

### 2a. Raw SQL to read option value
**File:** `XF/ForumType/Discussion.php:29-34`
Queries `xf_option` table directly with raw SQL. Violates XF resource standards.
**Fix:** Replace with `\XF::options()->taylorjBlogsBlogPostForum`.

### 2b. Copy-paste `resource_category` references
**File:** `XF/Entity/User.php:24-38`
`hasBlogPostPermission()` and `cacheResourceCategoryPermissions()` use `'resource_category'` content type from XFRM.
**Fix:** Convert to blog-appropriate content type, or remove if the methods are unused (check callers first).

### 2c. Missing primary key on `blog_watch` table
**File:** `Setup.php:103-107`
Table created without a primary key — violates schema standards.
**Fix:** Add `$table->addPrimaryKey(['user_id', 'blog_id'])` to both `installStep5()` and add an upgrade step for existing installs.

### 2d. `blog_post_title` column size mismatch
**File:** `Setup.php:56` creates `varchar(50)`, `Entity/BlogPost.php:563` allows `maxLength => 300`.
**Fix:** Add upgrade step to `ALTER` column to `varchar(300)`.

### 2e. Admin controller missing permission checks
**File:** `Admin/Controller/Blogs.php`
No `assertAdminPermission()` calls anywhere. Any admin can access blog management.
**Fix:** Add `assertAdminPermission('taylorjBlogs')` in `preDispatchController()` (register the admin permission if needed, or use an existing one).

---

## 3. Medium — Code Quality

### 3a. Remove dead `getTotalBlogPosts()` method
**File:** `Entity/Blog.php:262-268`
References non-existent entity `TaylorJ\Blogs:Post`, uses wrong ID (`$this->id`), returns a Finder instead of calling `->total()`. Method is never called.
**Fix:** Delete the method entirely.

### 3b. Remove unused `deleteBlogHeaderImageFiles()` with broken call
**File:** `Service/Blog/Edit.php:66-72`
Missing parentheses on `getBlogHeaderImage` (method invoked as property). Never called anywhere.
**Fix:** Delete the method.

### 3c. Fix duplicate filter calls in Blog controller
**File:** `Pub/Controller/Blog.php:148-149, 164-167`
`$this->filter('hard_delete', 'bool')` and `$this->filter('reason', 'str')` called before the POST check, then again inside it. The pre-check calls serve no purpose.
**Fix:** Remove the pre-POST filter calls (lines 148-149), keep only the ones inside `isPost()`.

### 3d. Use `assertBlogExists()` instead of manual Finder
**File:** `Pub/Controller/Blog.php:136`
Manual `finder()->where()->fetchOne()` instead of existing `assertBlogExists()` helper (line 384).
**Fix:** Replace with `$this->assertBlogExists($params->blog_id)`.

### 3e. Fix `blogMadeVisible()` always-truthy relation check
**File:** `Entity/Blog.php:330-340`
`if ($this->BlogPosts)` checks an AbstractCollection object which is always truthy.
**Fix:** Change to `if ($this->BlogPosts->count())`.

### 3f. XFRM naming remnant in Create service
**File:** `Service/BlogPost/Create.php:207`
Method named `afterResourceThreadCreated()` — copied from XFRM.
**Fix:** Rename to `afterBlogPostThreadCreated()`.

### 3g. Trailing underscore typo in Create service
**File:** `Service/BlogPost/Create.php:225`
`$blogPost->blog_post_title_` has a trailing underscore.
**Fix:** Change to `$blogPost->blog_post_title`.

---

## 4. Low — Data/Config Cleanup

### 4a. Fix "vendofr" typo in phrase
**File:** `_data/phrases.xml:167`
"vendofr" should be "vendor" in `taylorj_blogs_vendor_folder_missing`.
**Fix:** Correct the typo (edit via admin panel, then `xf:dev-export`, or edit XML directly).

### 4b. Remove duplicate widget definition
**File:** `_data/widget_definitions.xml`
Both `taylorj_blo_similar_posts` and `taylorj_similar_posts` point to `TaylorJ\Blogs\Widget\SimilarBlogPosts`.
**Fix:** Remove the duplicate entry. Keep whichever is referenced elsewhere, remove the other.

---

## Files to Modify

| File | Items |
|------|-------|
| `Entity/Blog.php` | 1a, 1b, 3a, 3e |
| `Admin/Controller/Blogs.php` | 1c, 1d, 2e |
| `Service/BlogPost/Create.php` | 1e, 3f, 3g |
| `XF/ForumType/Discussion.php` | 2a |
| `XF/Entity/User.php` | 2b |
| `Setup.php` | 2c, 2d |
| `Service/Blog/Edit.php` | 3b |
| `Pub/Controller/Blog.php` | 3c, 3d |
| `_data/phrases.xml` | 4a |
| `_data/widget_definitions.xml` | 4b |

---

## Verification

1. Run existing tests: `cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs && XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/`
2. Rebuild addon: `php cmd.php xf:addon-rebuild TaylorJ/Blogs` (from XF root)
3. Export dev output: `php cmd.php xf:dev-export --addon=TaylorJ/Blogs`
4. Manual smoke tests:
   - Create a blog as regular user — verify `canView()` works with only `viewOwn`
   - Edit a blog as a moderator — verify `canEdit()` with `canEditAny`
   - Delete a blog from admin panel — verify delete action works
   - Create a blog post with title > 50 chars — verify it saves
   - Check admin panel requires permission
