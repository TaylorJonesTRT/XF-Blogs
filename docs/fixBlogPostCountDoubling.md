# Fix doubled blog_post_count on blog post save

## Context

Saving a new visible blog post leaves `xf_taylorj_blogs_blog.blog_post_count` one too high: the first post shows as "2 posts". The new blog index (`taylorj_blogs_index.html:39-40`) reads the stored column through `$blog.getValue('blog_post_count')`, so the bad value now shows up there.

**Root cause:** `Entity/Blog.php:488` registers a getter for `blog_post_count`. Because of it, `$blog->blog_post_count` runs `getBlogPostCount()`, a live `COUNT` of visible posts, and never reads the column. `Utils::adjustBlogPostCount()` (`Utils.php:160`) does `fastUpdate('blog_post_count', $blog->blog_post_count + $amount)`. It runs from `BlogPost::_postSave()`, after the new row is already inserted, so the live count already includes the new post and the `+1` counts it a second time. Deleting a post has the same problem in reverse: the live count already excludes the post, and then 1 more is subtracted.

There is a second, smaller drift in `BlogPost::_postSave()` (`Entity/BlogPost.php:510-522`). Every insert increments both counts, drafts, scheduled and moderated posts included. When one of those later becomes visible it increments again. `_postDelete` also decrements whatever state the post was in, and a soft delete (visible → deleted) never decrements. The rebuild paths (`Repository/Blog::batchUpdateBlogPostCounts`, `Job/UserBlogPostCountTotal`) count visible posts only, so the incremental updates should do the same.

## Changes

1. **`Utils.php`, `adjustBlogPostCount()`:** read the stored column, not the getter:
   `max(0, $blog->getValue('blog_post_count') + $amount)`.
   `adjustUserBlogPostCount()` is fine, because `taylorj_blogs_blog_post_count` on User has no getter.

2. **`Entity/BlogPost.php`, `_postSave()`:** adjust counts only when visibility changes. XF's `isStateChanged()` returns `'enter'` on an insert whose state is `visible`, so one check covers both new posts and later publishes.
   - Replace the `!isUpdate()` block and the `isUpdate() && $visibilityChange == 'enter'` block with a single `if ($visibilityChange == 'enter')` that does +1 on the blog count, +1 on the user count, and sets `blog_last_post_date`. Keep the `if ($approvalChange)` date bump and ham submit inside it. They are no-ops on insert.
   - Add `else if ($visibilityChange == 'leave')` with -1 on both counts. This covers soft delete, unapprove, and moving a post back to draft.
   - Keep the deletion-log, approval-queue and job handling as they are (still update-only).
   - Side effect: inserting a draft or scheduled post no longer touches `blog_last_post_date`. That's intended, since the date is set when the post goes visible.

3. **`Entity/BlogPost.php`, `_postDelete()`:** decrement only when `$this->blog_post_state == 'visible'`. A soft-deleted post was already decremented on leave, and a draft was never counted.

4. **`tests/Unit/UtilsTest.php`:** the `adjustBlogPostCount` tests mock `offsetGet`/`get('blog_post_count')`. Switch them to mock `getValue('blog_post_count')` so they exercise the stored column. Add a case where the getter would return a different value, to guard against regressing.

## Verification

- `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit'`
- Manually, on the dev site:
  1. Reset counts: `UPDATE xf_taylorj_blogs_blog SET blog_post_count = 0` (or run the cron `runBlogPostCountUpdate`).
  2. Create a visible post, check the index shows "1 post" and `SELECT blog_post_count FROM xf_taylorj_blogs_blog` = 1, and `xf_user.taylorj_blogs_blog_post_count` = 1.
  3. Create a draft: counts stay at 1. Publish it via edit: counts go to 2.
  4. Soft-delete one: back to 1. Hard-delete the other: 0.
  5. Create a scheduled post and run the scheduled job: increments once.
- Existing bad counts in the database correct themselves through the existing cron (`Cron/Blog::runBlogPostCountUpdate`) and the `UserBlogPostCountTotal` rebuild job. No upgrade step is needed.
