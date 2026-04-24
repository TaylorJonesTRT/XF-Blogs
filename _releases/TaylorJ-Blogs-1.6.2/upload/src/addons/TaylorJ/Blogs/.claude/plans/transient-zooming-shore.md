# Plan: Finder Refactoring (Revised)

## Context

The Blogs add-on has a single `Finder/BlogPost.php` that is barely used — its `applyGlobalVisibilityChecks()` has been silently broken since it was written (wrong permission group/key names), its PHPDoc references the wrong entity (`\XFRM\Entity\ResourceItem`), and two of its methods reference non-existent columns/relations. There is no `Finder/Blog.php` at all. All state/visibility filtering is done ad-hoc inline with raw `where('blog_post_state', ...)` calls scattered across controllers and repositories — some missing state filters entirely. This refactoring creates a proper, fully fluent Finder layer following XenForo's own `ThreadFinder`/`PostFinder` patterns, and fixes all bugs uncovered in the process.

---

## Scrutiny Corrections (changes from first draft)

1. **Permission keys in BlogPost Finder were wrong** — existing code uses `'taylorjBlogsBlogPost', 'viewDeleted'` (group doesn't exist) and `'taylorjBlogsBlogPost', 'viewModerated'` (group doesn't exist). Actual permissions.xml has:
   - Group `taylorjBlogPost`, ID `canViewDeletedBlogPosts`
   - Group `taylorjBlogPost`, ID `canViewModerated`
   All visibility methods must use these corrected keys.

2. **Blog Finder `applyGlobalVisibilityChecks()` invented permissions** — The `taylorjBlogs` group has NO `viewDeleted` or `viewModerated` IDs. The method must use existing proxies: `canDeleteAny` for deleted, and omit moderated (no blog-level moderated-view permission exists yet).

3. **`batchUpdateBlogPostCounts()` over-restricts** — Filtering to visible only means moderated blogs get stale counts before approval. Should update all non-deleted blogs (`['visible', 'moderated']`).

4. **`renderBlogPostList()` missing guards** — `actionDraftPosts()` and `actionDeletedPosts()` need permission/ownership checks before delegating to the helper.

5. **`FindNew/BlogPosts.php`** was identified during exploration but dropped from the original plan. Adding it back.

---

## Files to Create

### `Finder/Blog.php` (new)

```php
<?php

namespace TaylorJ\Blogs\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\Blog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\Blog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \TaylorJ\Blogs\Entity\Blog|null fetchOne(?int $offset = null)
 * @extends Finder<\TaylorJ\Blogs\Entity\Blog>
 */
class Blog extends Finder
{
	public function visibleBlogs()
	{
		$this->where('blog_state', 'visible');
		return $this;
	}

	public function deletedBlogs()
	{
		$this->where('blog_state', 'deleted');
		$this->with('DeletionLog');
		return $this;
	}

	public function moderatedBlogs()
	{
		$this->where('blog_state', 'moderated');
		return $this;
	}

	/**
	 * Permission-based visibility for blogs.
	 * NOTE: taylorjBlogs has no dedicated viewModerated permission; only deleted
	 * is gated (via canDeleteAny as proxy). Moderated blogs are not surfaced here
	 * — they are handled by the approval queue, not the public listing.
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogs', 'canDeleteAny'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		// No taylorjBlogs.viewModerated permission exists. Moderated blogs visible
		// to the owner only via allowOwnPending.
		if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	/**
	 * If the visitor lacks viewAny, restrict to their own blogs only.
	 * Used in the blog index listing.
	 */
	public function applyOwnershipVisibility()
	{
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('taylorjBlogs', 'viewAny'))
		{
			if ($visitor->user_id)
			{
				$this->where('user_id', $visitor->user_id);
			}
			else
			{
				$this->whereImpossible();
			}
		}
		return $this;
	}

	public function byUser(int $userId)
	{
		$this->where('user_id', $userId);
		return $this;
	}

	public function latestFirst()
	{
		$this->order('blog_last_post_date', 'DESC');
		return $this;
	}

	public function newestFirst()
	{
		$this->order('blog_creation_date', 'DESC');
		return $this;
	}

	public function useDefaultOrder()
	{
		$this->setDefaultOrder('blog_last_post_date', 'desc');
		return $this;
	}
}
```

---

## Files to Modify

### `Finder/BlogPost.php` — full replacement

Fix PHPDoc (was `\XFRM\Entity\ResourceItem`), fix permission keys (were wrong group/ID), add all state/context/ordering methods, remove broken `forFullView()` (references non-existent `fullCategory` alias), fix `useDefaultOrder()` (referenced non-existent `last_update` column).

**Corrected permission keys:**
- `'taylorjBlogsBlogPost', 'viewDeleted'` → `'taylorjBlogPost', 'canViewDeletedBlogPosts'`
- `'taylorjBlogsBlogPost', 'viewModerated'` → `'taylorjBlogPost', 'canViewModerated'`

```php
<?php

namespace TaylorJ\Blogs\Finder;

use TaylorJ\Blogs\Entity\Blog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\BlogPost> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\TaylorJ\Blogs\Entity\BlogPost> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \TaylorJ\Blogs\Entity\BlogPost|null fetchOne(?int $offset = null)
 * @extends Finder<\TaylorJ\Blogs\Entity\BlogPost>
 */
class BlogPost extends Finder
{
	// -------------------------------------------------------------------------
	// Context methods (mirror XF PostFinder::inThread pattern)
	// -------------------------------------------------------------------------

	/**
	 * Scope to a blog and apply permission-based visibility.
	 * Primary entry-point for listing posts within a specific blog.
	 */
	public function inBlog(Blog $blog, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => true,
		], $limits);

		$this->where('blog_id', $blog->blog_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInBlog($limits['allowOwnPending']);
		}

		return $this;
	}

	/**
	 * Per-blog visibility — uses correct taylorjBlogPost permission IDs.
	 * Mirrors XF PostFinder::applyVisibilityChecksInThread().
	 */
	public function applyVisibilityChecksInBlog(bool $allowOwnPending = true)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_post_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_post_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	/**
	 * Global (cross-blog) visibility — used in "What's New", widgets, etc.
	 * Uses correct permission IDs (was broken in original with wrong group name).
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false)
	{
		$visitor = \XF::visitor();
		$conditions = [];
		$viewableStates = ['visible'];

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
		{
			$viewableStates[] = 'deleted';
			$this->with('DeletionLog');
		}

		if ($visitor->hasPermission('taylorjBlogPost', 'canViewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		else if ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'blog_post_state' => 'moderated',
				'user_id' => $visitor->user_id,
			];
		}

		$conditions[] = ['blog_post_state', $viewableStates];
		$this->whereOr($conditions);
		return $this;
	}

	// -------------------------------------------------------------------------
	// State filter methods
	// -------------------------------------------------------------------------

	public function visiblePosts()
	{
		$this->where('blog_post_state', 'visible');
		return $this;
	}

	public function draftPosts()
	{
		$this->where('blog_post_state', 'draft');
		return $this;
	}

	public function scheduledPosts()
	{
		$this->where('blog_post_state', 'scheduled');
		return $this;
	}

	public function deletedPosts()
	{
		$this->where('blog_post_state', 'deleted');
		$this->with('DeletionLog');
		return $this;
	}

	public function moderatedPosts()
	{
		$this->where('blog_post_state', 'moderated');
		return $this;
	}

	// -------------------------------------------------------------------------
	// Context filter methods
	// -------------------------------------------------------------------------

	/** Scope to a blog by ID (when you don't have the entity). */
	public function inBlogId(int $blogId)
	{
		$this->where('blog_id', $blogId);
		return $this;
	}

	public function byUser(int $userId)
	{
		$this->where('user_id', $userId);
		return $this;
	}

	public function forThread(int $threadId)
	{
		$this->where('discussion_thread_id', $threadId);
		return $this;
	}

	// -------------------------------------------------------------------------
	// Ordering methods
	// -------------------------------------------------------------------------

	public function latestFirst()
	{
		$this->order('blog_post_date', 'DESC');
		return $this;
	}

	public function oldestFirst()
	{
		$this->order('blog_post_date', 'ASC');
		return $this;
	}

	public function randomOrder()
	{
		$this->order($this->expression('RAND()'));
		return $this;
	}

	public function useDefaultOrder()
	{
		$this->setDefaultOrder('blog_post_date', 'desc');
		return $this;
	}

	// -------------------------------------------------------------------------
	// Retained from original
	// -------------------------------------------------------------------------

	public function watchedOnly($userId = null)
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			return $this;
		}

		$this->whereOr(
			['Watch|' . $userId . '.user_id', '!=', null],
			['BlogPost.Watch|' . $userId . '.user_id', '!=', null]
		);

		return $this;
	}
}
```

---

### `Repository/BlogPost.php` — update finder methods

**`findLatestBlogPosts()`** — fix wrong `@return ThreadFinder` annotation, use chain methods:
```php
/** @return BlogPostFinder */
public function findLatestBlogPosts()
{
    return $this->finder(BlogPostFinder::class)
        ->visiblePosts()
        ->latestFirst();
}
```

**`findBlogPostsByUser()`:**
```php
public function findBlogPostsByUser(int $userId)
{
    return $this->finder(BlogPostFinder::class)
        ->byUser($userId)
        ->visiblePosts()
        ->latestFirst();
}
```

**`findOtherPostsByOwnerRandom()`** — BUG FIX: add missing `visiblePosts()` (was returning drafts/deleted/scheduled):
```php
public function findOtherPostsByOwnerRandom(int $userId)
{
    return $this->finder(BlogPostFinder::class)
        ->byUser($userId)
        ->visiblePosts()
        ->randomOrder();
}
```

**`findBlogPostForThread()`:**
```php
public function findBlogPostForThread(Thread $thread)
{
    return $this->finder(BlogPostFinder::class)
        ->forThread($thread->thread_id);
}
```

---

### `Repository/Blog.php` — add BlogFinder import, update methods

Add import: `use TaylorJ\Blogs\Finder\Blog as BlogFinder;`

**`findBlogsByUser()`:**
```php
public function findBlogsByUser(int $userId)
{
    return $this->finder(BlogFinder::class)
        ->byUser($userId)
        ->latestFirst();
}
```

**`batchUpdateBlogPostCounts()`** — BUG FIX: was fetching ALL blogs, update to non-deleted only (visible + moderated) so moderated blogs awaiting approval keep accurate counts:
```php
// Before (fetches ALL blogs including deleted):
$blogs = $this->finder('TaylorJ\Blogs:Blog')->fetch();

// After (non-deleted only):
$blogs = $this->finder(BlogFinder::class)
    ->where('blog_state', ['visible', 'moderated'])
    ->fetch();
```

---

### `Pub/Controller/Blogs.php` — fix security bug + use Blog Finder

**`actionIndex()`** — `$blogFinder` was overwritten on line 37, discarding the `viewAny` ownership filter:

```php
// After:
$blogFinder = $this->finder('TaylorJ\Blogs:Blog');
$blogFinder
    ->visibleBlogs()
    ->applyOwnershipVisibility()
    ->latestFirst();
```

---

### `Pub/Controller/Blog.php` — extract helper, fix double-fetch, fix content type, add permission guards

Replace `getBlogPosts()` helper and all four action methods. The `renderBlogPostList()` helper handles shared pagination/attachment/inline-mod logic. Each action adds its own ownership/permission guard BEFORE calling the helper.

```php
// REMOVE: getBlogPosts()

// ADD: shared helper
protected function renderBlogPostList(BlogEntity $blog, string $viewType)
{
    /** @var \TaylorJ\Blogs\Finder\BlogPost $blogPostFinder */
    $blogPostFinder = $this->finder('TaylorJ\Blogs:BlogPost');

    switch ($viewType)
    {
        case 'visible':
            // inBlog() applies permission-based visibility checks
            $blogPostFinder->inBlog($blog);
            break;
        case 'scheduled':
            $blogPostFinder->inBlogId($blog->blog_id)->scheduledPosts();
            break;
        case 'draft':
            $blogPostFinder->inBlogId($blog->blog_id)->draftPosts();
            break;
        case 'deleted':
            $blogPostFinder->inBlogId($blog->blog_id)->deletedPosts();
            break;
    }

    $blogPostFinder->latestFirst();

    $page = $this->filterPage();
    $perPage = $this->options()->taylorjBlogPostsPerPage;
    $blogPostFinder->limitByPage($page, $perPage);

    /** @var AttachmentRepository $attachmentRepo */
    $attachmentRepo = \XF::repository(AttachmentRepository::class);
    $attachmentRepo->addAttachmentsToContent($blogPostFinder, 'taylorj_blogs_blog_post');

    $allBlogPosts = $blogPostFinder->fetch();  // single fetch, reused below

    $canInlineMod = false;
    foreach ($allBlogPosts AS $blogPost)
    {
        if ($blogPost->canUseInlineModeration())
        {
            $canInlineMod = true;
            break;
        }
    }

    return [
        'blog'           => $blog,
        'blogPosts'      => $allBlogPosts,
        'page'           => $page,
        'perPage'        => $perPage,
        'total'          => $blogPostFinder->total(),
        'allowInlineMod' => !$this->request->get('_xfDisableInlineMod'),
        'canInlineMod'   => $canInlineMod,
        'viewType'       => $viewType,
    ];
}

// Updated action methods with permission guards:
public function actionIndex(ParameterBag $params)
{
    $blog = $this->assertBlogExists($params->blog_id);
    return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
        $this->renderBlogPostList($blog, 'visible'));
}

public function actionScheduledPosts(ParameterBag $params)
{
    $blog = $this->assertBlogExists($params->blog_id);
    if (!$blog->canViewScheduledPosts())
    {
        return $this->noPermission();
    }
    return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
        $this->renderBlogPostList($blog, 'scheduled'));
}

public function actionDraftPosts(ParameterBag $params)
{
    $blog = $this->assertBlogExists($params->blog_id);
    // Drafts are private — only the blog owner can see them
    if ($blog->user_id !== \XF::visitor()->user_id)
    {
        return $this->noPermission();
    }
    return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
        $this->renderBlogPostList($blog, 'draft'));
}

public function actionDeletedPosts(ParameterBag $params)
{
    $blog = $this->assertBlogExists($params->blog_id);
    if (!\XF::visitor()->hasPermission('taylorjBlogPost', 'canViewDeletedBlogPosts'))
    {
        return $this->noPermission();
    }
    return $this->view('TaylorJ\Blogs:Blog\Index', 'taylorj_blogs_blog_view',
        $this->renderBlogPostList($blog, 'deleted'));
}
```

> **Bugs fixed:**
> - Double `->fetch()` in `actionDraftPosts()` (lines 153+165) and `actionDeletedPosts()` (lines 200+212)
> - Wrong content type `'post'` → `'taylorj_blogs_blog_post'` in `actionScheduledPosts()`
> - Missing ownership guard on `actionDraftPosts()`
> - Missing permission guard on `actionDeletedPosts()`

---

### `Widget/SimilarBlogPosts.php`

```php
// Before:
$finder = $this->finder('TaylorJ\Blogs:BlogPost')
    ->whereIds($similarBlogPostIds)
    ->where('blog_post_state', 'visible');

// After:
$finder = $this->finder('TaylorJ\Blogs:BlogPost')
    ->whereIds($similarBlogPostIds)
    ->visiblePosts();
```

---

### `Entity/Blog.php` — `getBlogPostCount()`

```php
// Before:
return $this->finder('TaylorJ\Blogs:BlogPost')
    ->where('blog_id', $this->blog_id)
    ->where('blog_post_state', 'visible')
    ->total();

// After:
return $this->finder('TaylorJ\Blogs:BlogPost')
    ->inBlogId($this->blog_id)
    ->visiblePosts()
    ->total();
```

---

### `FindNew/BlogPosts.php` — add visibility filter (was missing from original plan)

The "What's New" integration currently shows draft, scheduled, moderated, and deleted posts to all users. Add `applyGlobalVisibilityChecks()`:

```php
// Before (no state filter at all):
$finder = \XF::finder('TaylorJ\Blogs:BlogPost')
    ->order('blog_post_date', 'DESC');

// After:
$finder = \XF::finder('TaylorJ\Blogs:BlogPost');
$finder
    ->applyGlobalVisibilityChecks()
    ->latestFirst();
```

---

## Implementation Order

1. Create `Finder/Blog.php`
2. Replace `Finder/BlogPost.php` (with corrected permission keys)
3. Update `Repository/Blog.php`
4. Update `Repository/BlogPost.php`
5. Fix `Pub/Controller/Blogs.php` (security bug)
6. Refactor `Pub/Controller/Blog.php` (helper, permission guards, double-fetch, content type)
7. Update `Widget/SimilarBlogPosts.php`
8. Update `Entity/Blog.php` (`getBlogPostCount`)
9. Update `FindNew/BlogPosts.php`
10. Rebuild add-on and run tests

## Verification

```bash
# Rebuild addon cache
php cmd.php xf:addon-rebuild TaylorJ/Blogs

# Run test suite
cd /Users/taylorjones/Herd/xf232/src/addons/TaylorJ/Blogs
XDEBUG_MODE=off ./vendor/bin/phpunit tests/Unit/Entity/ tests/Unit/Job/

# Manual testing checklist:
# - /blogs — blog index (verify viewAny permission correctly restricts to own blogs)
# - /blogs/blog/{id} — visible posts list
# - /blogs/blog/{id}/scheduled-posts — only blog owner; verify attachments load correctly
# - /blogs/blog/{id}/draft-posts — only blog owner; verify no double DB query
# - /blogs/blog/{id}/deleted-posts — only canViewDeletedBlogPosts; verify no double query
# - Blog post view page — "other posts by author" widget (verify no drafts/deleted shown)
# - What's New — verify only visible blog posts appear
```
