# Fix: Draft posts not transitioning to visible properly

## Context

When editing a draft blog post and changing it to "post now" (visible), the `blog_post_date` column stays at `0` and the post doesn't appear in any visible post listings. This is because the date is never set during the draft→visible transition in the edit flow.

## Root Cause

There are two bugs working together:

### Bug 1: `setScheduledPostDateTime()` doesn't set the date for "post now"
**File:** `Service/BlogPost/Edit.php:190-194`

When the user selects "post now", the else branch sets the state but never sets `blog_post_date`:
```php
else  // 'visible' / 'post now'
{
    $this->blogPost->scheduled_post_date_time = 0;
    $this->blogPost->blog_post_state = $this->blogPost->getNewContentState();
    // blog_post_date is NEVER set here
}
```

### Bug 2: `_save()` skips date setting for visible posts
**File:** `Service/BlogPost/Edit.php:66-68`

The `_save()` method only sets the date when the state is NOT visible, but by this point the state has already been changed to visible:
```php
if ($blogPost->blog_post_state != 'visible')  // already 'visible', so skipped
{
    $blogPost->fastUpdate('blog_post_date', \XF::$time);
}
```

### Bug 3: `finalSteps()` never called after edit
**File:** `Pub/Controller/BlogPost.php:102`

The controller calls `$editor->save()` but never calls `$editor->finalSteps()`, so thread creation (for blog post comments) doesn't happen when a draft becomes visible via editing.

## Fix

### 1. Set `blog_post_date` in `setScheduledPostDateTime()` — `Service/BlogPost/Edit.php:190-194`

Add `$this->blogPost->blog_post_date = \XF::$time;` in the else branch (post now):

```php
else
{
    $this->blogPost->scheduled_post_date_time = 0;
    $this->blogPost->blog_post_date = \XF::$time;  // ADD THIS
    $this->blogPost->blog_post_state = $this->blogPost->getNewContentState();
}
```

### 2. Call `finalSteps()` after save in controller — `Pub/Controller/BlogPost.php:102`

Add `$editor->finalSteps();` after `$editor->save();` so thread creation happens:

```php
$editor->save();
$editor->finalSteps();
```

### 3. Fix `_save()` date logic — `Service/BlogPost/Edit.php:62-79`

The current `_save()` logic is inverted — it updates the date only for non-visible posts and sets edit date only for visible posts. After the fix in step 1, the date will already be set correctly on the entity before save, so the `_save()` method's `fastUpdate` for `blog_post_date` becomes a fallback. However, we should also fix the logic to handle the transition case properly by checking if this is a state change to visible:

```php
protected function _save()
{
    $blogPost = $this->blogPost;

    if ($blogPost->blog_post_state == 'visible' && $blogPost->isChanged('blog_post_state'))
    {
        $blogPost->fastUpdate('blog_post_date', \XF::$time);
    }

    if ($blogPost->blog_post_state == 'visible' && $blogPost->blog_post_date <= \XF::$time)
    {
        $blogPost->fastUpdate('blog_post_last_edit_date', \XF::$time);
    }

    $blogPost->save(true, false);

    return $blogPost;
}
```

## Files to modify

1. `Service/BlogPost/Edit.php` — Set date in `setScheduledPostDateTime()`, fix `_save()` logic
2. `Pub/Controller/BlogPost.php` — Call `finalSteps()` after save

## Verification

1. Create a new blog post as a draft
2. Edit the draft and change it to "post now"
3. Verify `blog_post_date` is set (check DB: `SELECT blog_post_date FROM xf_taylorj_blogs_blog_post WHERE blog_post_id = X`)
4. Verify the post appears in the blog's post listing
5. Verify the post appears in "What's New" / latest blog posts
6. If blog post comments are enabled, verify a discussion thread is created
7. Test that editing an already-visible post still works correctly (should update last edit date)
8. Test that scheduling and draft creation still work as expected
