# Blog post comments are forum threads

Blog posts have no comment system of their own. When `taylorjBlogsBlogPostComments` is on, publishing a post creates a **Discussion Thread** in the configured **Comment forum** (`BlogPost\ThreadCreator`), and the post keeps the link in `discussion_thread_id`. We chose this because it reuses XenForo's replies, moderation, reactions, alerts, and search for free. The cost is that comments depend on forum configuration.

## Consequences

- These threads use a custom `blogPost` thread type (`ThreadType/BlogPostHandler.php`), which shows the blog post above the thread. The type can't be created externally or converted to or from.
- `XF/ForumType/Discussion.php` adds `blogPost` to the allowed thread types but removes it from the creatable list, so users can't start these threads by hand. It is also hidden from thread filters when no Comment forum is set.
- Deleting a blog post doesn't delete its thread. `taylorjBlogsBlogPostDeleteThreadAction` decides whether to close it, retitle it, or add a post.
- Posts created while comments were off have no thread. `Job/BlogPostThreadCreation.php` backfills them.
