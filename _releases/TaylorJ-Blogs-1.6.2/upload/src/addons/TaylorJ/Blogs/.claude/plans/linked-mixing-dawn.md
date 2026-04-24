# Skinny Controllers Refactoring Plan

## Context

The public controllers contain business logic that should live in services, repositories, or entity methods. This refactoring extracts that logic to make controllers thin orchestrators — easier to test and maintain. Four extractions are proposed, ordered by impact.

## Changes

### 1. Extract header image handling → `Repository/Blog.php`

**Problem:** `Blogs.php:137-159` has ~22 lines of conditional header image upload/delete logic. This same pattern would need duplicating anywhere blog header images are saved.

**New method:** `Repository/Blog::handleHeaderImageFromInput(BlogEntity $blog, ?string $headerAction, ?\XF\Http\Upload $upload): void`

Moves the `upload_header` / `delete_header` / fallback conditional into the repo, which already has `setBlogHeaderImagePath()` and `deleteBlogHeaderImage()`.

**Controller after** (`Blogs.php:137-159` replaced with):
```php
$blogHeaderImage = $this->filter('taylorj_blogs_blog_header_image_confirm', 'str');
$upload = $this->request->getFile('upload', false, false);
Utils::getBlogRepo()->handleHeaderImageFromInput($blog, $blogHeaderImage ?: null, $upload ?: null);
```

---

### 2. Extract read time calculation → `Entity/BlogPost.php`

**Problem:** `BlogPost.php:64-65` has inline word-count-to-read-time math. This is a property of the content itself.

**New method:** `Entity/BlogPost::getReadTimeMinutes(int $wordsPerMinute = 225): int`

Register as a getter in `getStructure()` so templates can use `$blogPost.read_time_minutes`.

**Controller after** (`BlogPost.php:64-65` + viewParams):
```php
'blogPostReadTime' => $blogPost->read_time_minutes,
```

---

### 3. Extract scheduling view params → `Utils.php`

**Problem:** `Blog.php:253-259` and `BlogPost.php:116-125` both build identical DateTime/hours/minutes arrays for the scheduling form.

**New method:** `Utils::getSchedulingViewParams(?int $timestamp = null): array`

Returns `['hours', 'minutes', 'dt', 'hh_value', 'mm_value']`. Uses `\XF::visitor()->timezone`. If `$timestamp` is provided, sets it on the DateTime (for editing existing scheduled posts).

**Controller after** (`Blog.php:253-272`):
```php
$schedulingParams = Utils::getSchedulingViewParams();
$viewParams = [
    'blogPost'       => $blogPost,
    'blog'           => $blog,
    'attachmentData' => $attachmentData,
    'blogId'         => $blog_id,
] + $schedulingParams;
```

**Controller after** (`BlogPost.php:116-138`):
```php
$schedulingParams = Utils::getSchedulingViewParams($blogPost->scheduled_post_date_time);
$viewParams = [
    'blogPost'       => $blogPost,
    'blog'           => $blog,
    'attachmentData' => $attachmentData,
    'blog_id'        => $blogId,
] + $schedulingParams;
```

---

### 4. Extract thread/comment fetching → `Repository/BlogPost.php`

**Problem:** `BlogPost.php:37-56` has inline finder logic to fetch the discussion thread and its latest comments. This is data-access logic.

**New method:** `Repository/BlogPost::getDiscussionCommentsForPost(BlogPostEntity $blogPost, int $limit = 5): array`

Returns `['thread' => Thread|null, 'comments' => AbstractCollection|null]`.

**Controller after** (`BlogPost.php:37-56`):
```php
$discussion = $blogPostRepo->getDiscussionCommentsForPost($blogPost, 5);
```

Then in viewParams use `$discussion['thread']` and `$discussion['comments']`.

---

### Not changed

- **`Pub/Controller/Author.php`** — Pagination boilerplate is standard XenForo controller code, not worth abstracting.
- **`Pub/Controller/WhatsNewBlogPosts.php`** — Already thin.
- **Content moderation session flag** (`setHasContentPendingApproval`) — This is a 2-line HTTP/session concern that correctly lives in controllers; moving it to services would violate layer separation.

## Files Modified

| File | Action |
|------|--------|
| `Repository/Blog.php` | Add `handleHeaderImageFromInput()` |
| `Entity/BlogPost.php` | Add `getReadTimeMinutes()` + getter registration |
| `Utils.php` | Add `getSchedulingViewParams()` |
| `Repository/BlogPost.php` | Add `getDiscussionCommentsForPost()` |
| `Pub/Controller/Blogs.php` | Simplify `blogSaveProcess()` lines 137-159 |
| `Pub/Controller/Blog.php` | Simplify `blogPostAdd()` lines 253-272 |
| `Pub/Controller/BlogPost.php` | Simplify `actionIndex()` lines 37-65 and `actionEdit()` lines 116-138 |

## Implementation Order

1. `Entity/BlogPost.php` — `getReadTimeMinutes()` (standalone, no deps)
2. `Utils.php` — `getSchedulingViewParams()` (standalone)
3. `Repository/Blog.php` — `handleHeaderImageFromInput()` (standalone)
4. `Repository/BlogPost.php` — `getDiscussionCommentsForPost()` (standalone)
5. `Pub/Controller/BlogPost.php` — Update `actionIndex()` and `actionEdit()`
6. `Pub/Controller/Blog.php` — Update `blogPostAdd()`
7. `Pub/Controller/Blogs.php` — Update `blogSaveProcess()`

## Verification

1. Run existing tests: `cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs && XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/`
2. Run addon rebuild: `php cmd.php xf:addon-rebuild TaylorJ/Blogs` (from XF root)
3. Manual browser testing: create/edit a blog (header image upload/delete), create/edit a blog post (scheduling form), view a blog post (read time, comments)
4. Format: `php vendor/bin/php-cs-fixer fix src/addons/TaylorJ/Blogs/`
