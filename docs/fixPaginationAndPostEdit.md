# Fix blog pagination and admin edit of other users' posts (1.8.1)

## Context

Two bugs reported in Blogs 1.8.0:

1. **Pagination on a blog page always shows page 1.** The `blogs/blog` route format is
   `blog/:int<blog_id,blog_title>/:page`, so `page-2` is parsed into the route `ParameterBag`, not the
   request input. `Pub/Controller/Blog.php::renderBlogPostList()` calls `$this->filterPage()` with no
   argument, which only reads `?page=` from the request (`XF\Mvc\Controller::filterPage()`, line 852),
   so it always resolves to 1. The user's separate workaround add-on copies the route page into the
   request; the proper fix is the first-party pattern `$this->filterPage($params->page)` (as in
   `XF/Pub/Controller/FeaturedContentController.php:35`, `AbstractWhatsNewFindType.php:52`).
   - `Blogs::actionIndex` already reads `$params->page`; `Author` routes have no `:page`, so their
     `?page=` links work. Neither needs changing.
2. **Admins can't edit other users' blog posts.** `Entity/BlogPost.php::canEdit()` (lines 142–166)
   checks `taylorjBlogs/canEditAny` (the *blog* permission) instead of `taylorjBlogPost/canEditAny`
   (exists: `_output/permissions/taylorjBlogPost-canEditAny.json`), and both branches of the non-author
   path return `false`.

The working tree is already at 1.8.1 (`version_id` 1080170), so no version bump is needed.

## Changes

### 1. `Pub/Controller/Blog.php` — read the route page
- Give `renderBlogPostList()` a `ParameterBag $params` (or `int $page`) argument and use
  `$page = $this->filterPage($params->page);`.
- Pass `$params` from the four callers: `actionIndex`, `actionScheduledPosts`, `actionDraftPosts`,
  `actionDeletedPosts`.

### 2. `_output/templates/public/taylorj_blogs_blog_view.html` — pagenav target (line 128)
- `data="{$blogPost}"` uses the leftover `foreach` variable; change to `data="{$blog}"` so the link
  carries the blog's id/slug.
- Scheduled/draft/deleted views currently paginate back to the visible index. Have
  `renderBlogPostList()` return a `pageNavLink` (`blogs/blog`, `blogs/blog/scheduled-posts`,
  `blogs/blog/draft-posts`, `blogs/blog/deleted-posts`) and use `link="{$pageNavLink}"`.
- Then: `xf xf-dev:import-templates --addon=TaylorJ/Blogs` (narrowest importer; never hand-edit
  `_metadata.json` or `_data/`).

### 3. `Entity/BlogPost.php::canEdit()` — mirror `Entity/Blog.php::canEdit()` (lines 62–83)
```php
} else
{
    if (!$visitor->hasPermission('taylorjBlogPost', 'canEditAny'))
    {
        $error = \XF::phrase('taylorj_blogs_blog_post_error_edit');
        return false;
    }
}

return true;
```

### 4. `tests/Unit/Entity/BlogPostTest.php` — canEdit coverage
- Fix `testCanEditReturnsFalseForNonOwner` to set `taylorjBlogPost => ['canEditAny' => false]`.
- Add `testCanEditReturnsTrueForNonOwnerWithEditAny` (`taylorjBlogPost/canEditAny = true`, visitor 999,
  post user 1) — this is the regression test; it fails on current code.
- Add `testCanEditReturnsFalseForNonOwnerWithOnlyBlogEditAny` (`taylorjBlogs/canEditAny = true`) to pin
  the permission group.
- Update the `canEdit()` test count in `tests/README.md` if it lists BlogPost's.

### 5. Housekeeping
- Copy this plan to `docs/fixPaginationAndPostEdit.md` (user's plan-file convention).
- After deploying 1.8.1 the workaround add-on can be uninstalled; it would be harmless but redundant.

## Verification
1. `xf exec xf sh -c 'cd src/addons/TaylorJ/Blogs && vendor/bin/phpunit tests/Unit/Entity/BlogPostTest.php'`
   — new non-owner test red before step 3, green after; then the full suite.
2. In the dev site (Chrome) with the workaround add-on disabled and `taylorjBlogPostsPerPage` lower
   than a blog's post count: open `blogs/blog/<slug>.<id>/page-2` and confirm different posts and the
   active page in the pagenav; repeat on `scheduled-posts`/`deleted-posts` that pagenav stays on that view.
3. As an admin with `taylorjBlogPost/canEditAny`, confirm the Edit control shows on another user's
   post and the edit form saves; as a regular member, confirm it is still denied.
