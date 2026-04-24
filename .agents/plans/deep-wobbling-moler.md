# Skinny Controllers Refactoring Plan (Revised)

## Context

Public controllers contain business logic (read time math, scheduling form construction, header image orchestration, thread/comment fetching) that should live in entities, repositories, or utility methods. Extracting this logic makes controllers thin orchestrators — easier to test, easier to reuse, and harder to get wrong when the same operation is needed elsewhere.

This is a revision of the original plan at `.claude/plans/linked-mixing-dawn.md`, fixing gaps found during scrutiny (missing `fastUpdate` calls, ambiguous viewParam keys, dead variables).

## Changes

### 1. Read time getter → `Entity/BlogPost.php`

**Current:** `Pub/Controller/BlogPost.php:64-65` computes read time inline.

**Add method:**
```php
public function getReadTimeMinutes(int $wordsPerMinute = 225): int
{
    return (int) ceil(str_word_count(strip_tags($this->blog_post_content)) / $wordsPerMinute);
}
```

**Register in `getStructure()`:** Add `'read_time_minutes' => true` to the getters array (line ~637).

**Update controller** (`BlogPost.php:64-65`): Remove the two inline lines. In `$viewParams`, change to:
```php
'blogPostReadTime' => $blogPost->read_time_minutes,
```

**Templates:** No change needed — `taylorj_blogs_blog_post_view.html` uses `$blogPostReadTime` which the controller still passes under that key.

---

### 2. Scheduling view params → `Utils.php`

**Current:** `Blog.php:253-259` and `BlogPost.php:116-125` both construct identical DateTime/hours/minutes arrays. `BlogPost.php` also has a dead `$tz` variable.

**Add static method to `Utils.php`:**
```php
public static function getSchedulingViewParams(?int $timestamp = null): array
{
    $dt = new \DateTime();
    $dt->setTimezone(new \DateTimeZone(\XF::visitor()->timezone));
    if ($timestamp !== null)
    {
        $dt->setTimestamp($timestamp);
    }
    return [
        'hours'    => static::hours(),
        'minutes'  => static::minutes(),
        'dt'       => $dt,
        'hh_value' => $dt->format('H'),
        'mm_value' => $dt->format('i'),
    ];
}
```

**Update `Blog.php` (`blogPostAdd`, ~lines 253-272):**
```php
$schedulingParams = Utils::getSchedulingViewParams();
$viewParams = [
    'blogPost'       => $blogPost,
    'blog'           => $blog,
    'attachmentData' => $attachmentData,
    'blogId'         => $blog_id,
] + $schedulingParams;
```

**Update `BlogPost.php` (`actionEdit`, ~lines 116-138):**
```php
$schedulingParams = Utils::getSchedulingViewParams($blogPost->scheduled_post_date_time);
$viewParams = [
    'blogPost'       => $blogPost,
    'blog'           => $blog,
    'attachmentData' => $attachmentData,
    'blog_id'        => $blogId,
] + $schedulingParams;
```

Note: `Blog.php` uses key `'blogId'` (camelCase) while `BlogPost.php` uses `'blog_id'` (snake_case). This pre-existing inconsistency matches what each template expects — leave as-is.

**Templates:** No change needed — keys `dt`, `hh_value`, `mm_value`, `hours`, `minutes` match exactly.

---

### 3. Header image handling → `Repository/Blog.php`

**Current:** `Blogs.php:137-159` has ~22 lines of conditional upload/delete logic including `fastUpdate('blog_has_header', ...)` calls.

**Add method to `Repository/Blog.php`:**
```php
public function handleHeaderImageFromInput(BlogEntity $blog, ?string $headerAction, ?\XF\Http\Upload $upload): void
{
    if ($headerAction === 'upload_header' || $headerAction === null)
    {
        if ($upload)
        {
            $this->setBlogHeaderImagePath($blog->blog_id, $upload);
            $blog->fastUpdate('blog_has_header', '1');
        }
    }
    elseif ($headerAction === 'delete_header')
    {
        $this->deleteBlogHeaderImage($blog);
        $blog->fastUpdate('blog_has_header', '0');
    }
}
```

This consolidates the three branches (upload_header, delete_header, fallback-to-upload) into two. The fallback case (no action selected but file present) has the same behavior as `upload_header`, so they merge cleanly.

**Update `Blogs.php` (lines 137-159):**
```php
$blogHeaderImage = $this->filter('taylorj_blogs_blog_header_image_confirm', 'str');
$upload = $this->request->getFile('upload', false, false);
Utils::getBlogRepo()->handleHeaderImageFromInput($blog, $blogHeaderImage ?: null, $upload ?: null);
```

**Templates:** No change needed — `taylorj_blogs_blog_edit.html` reads `$blog.blog_header_image` via entity getter.

---

### 4. Thread/comment fetching → `Repository/BlogPost.php`

**Current:** `BlogPost.php:37-56` has inline finder logic for discussion thread + comments.

**Add method to `Repository/BlogPost.php`:**
```php
public function getDiscussionCommentsForPost(BlogPostEntity $blogPost, int $limit = 5): array
{
    $discussionThread = null;
    $comments = null;

    if ($blogPost->blog_post_state === 'visible' && $blogPost->discussion_thread_id)
    {
        $discussionThread = \XF::finder('XF:Thread')
            ->where('thread_id', $blogPost->discussion_thread_id)
            ->fetchOne();
    }

    if ($discussionThread)
    {
        /** @var \XF\Repository\PostRepository $postRepo */
        $postRepo = \XF::app()->repository(\XF\Repository\PostRepository::class);
        $comments = $postRepo->findPostsForThreadView($discussionThread)
            ->order('post_date', 'DESC')
            ->fetch($limit);
    }

    return [
        'discussionThread' => $discussionThread,
        'comments' => $comments,
    ];
}
```

Key details:
- Return keys are `'discussionThread'` and `'comments'` — matching the template variable names exactly so they can be spread directly into `$viewParams`.
- `fetch($limit)` is called inside the method, returning a concrete collection (not a finder).
- Added a `$blogPost->discussion_thread_id` guard for safety.

**Update `BlogPost.php` (`actionIndex`, lines 37-56 + viewParams):**
```php
$discussion = Utils::getBlogPostRepo()->getDiscussionCommentsForPost($blogPost, 5);

// ... later in $viewParams:
'comments' => $discussion['comments'],
'discussionThread' => $discussion['discussionThread'],
```

Or more concisely, spread `$discussion` into `$viewParams` with `+ $discussion`.

**Templates:** No change needed — `taylorj_blogs_blog_post_view.html` uses `$discussionThread` and `$comments`, which match the return keys.

---

## Files Modified

| File | Action |
|------|--------|
| `Entity/BlogPost.php` | Add `getReadTimeMinutes()` + register getter |
| `Utils.php` | Add static `getSchedulingViewParams()` |
| `Repository/Blog.php` | Add `handleHeaderImageFromInput()` (absorbs `fastUpdate` calls) |
| `Repository/BlogPost.php` | Add `getDiscussionCommentsForPost()` |
| `Pub/Controller/BlogPost.php` | Simplify `actionIndex()` (lines 37-65) and `actionEdit()` (lines 116-125) |
| `Pub/Controller/Blog.php` | Simplify `blogPostAdd()` (lines 253-259) |
| `Pub/Controller/Blogs.php` | Simplify `blogSaveProcess()` (lines 137-159) |

**No template changes required** — all template variable names are preserved by the refactoring.

## Implementation Order

1. `Entity/BlogPost.php` — `getReadTimeMinutes()` (standalone)
2. `Utils.php` — `getSchedulingViewParams()` (standalone, uses existing `hours()`/`minutes()`)
3. `Repository/Blog.php` — `handleHeaderImageFromInput()` (uses existing repo methods)
4. `Repository/BlogPost.php` — `getDiscussionCommentsForPost()` (standalone)
5. `Pub/Controller/BlogPost.php` — Update `actionIndex()` and `actionEdit()` (uses #1, #2, #4)
6. `Pub/Controller/Blog.php` — Update `blogPostAdd()` (uses #2)
7. `Pub/Controller/Blogs.php` — Update `blogSaveProcess()` (uses #3)

## Verification

1. Run existing tests:
   ```bash
   cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs && XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/
   ```
2. Format code:
   ```bash
   php vendor/bin/php-cs-fixer fix src/addons/TaylorJ/Blogs/
   ```
3. Rebuild add-on:
   ```bash
   cd /Users/taylorjones/Herd/xf232 && php cmd.php xf:addon-rebuild TaylorJ/Blogs
   ```
4. Manual browser testing:
   - Create/edit a blog with header image upload and delete
   - Create a new blog post (check scheduling form defaults to current time)
   - Edit a scheduled blog post (check scheduling form shows the saved time)
   - View a blog post (check read time displays, comments load)
