# [TaylorJ] Blogs

A user blogging system for XenForo: members own blogs, publish posts to them, and readers discuss posts in linked forum threads.

## Language

### Blogs

**Blog**:
A member-owned container for blog posts, with a title, description, optional header image, and a state of `visible`, `moderated`, or `deleted`.
_Avoid_: user blog, journal

**Author**:
The member who owns a blog and writes its posts.
_Avoid_: blogger, owner (in UI copy)

**Header image**:
The optional banner image for a blog, stored at `data://taylorj_blogs/blog_header_images/{blog_id}.jpg`. It is stored at its original size with no resized variants, and also serves as the blog's square thumbnail (cropped with CSS).

**Blog index**:
The `/blogs/` page. It lists `visible` blogs the visitor may see (all of them with `viewAny`, otherwise only their own), ordered by the date of each blog's last post.
_Avoid_: blog list, blog directory

**Blog creation limit**:
The most blogs a member may own, set by the `blogCreationLimit` integer permission (`-1` = unlimited).
_Avoid_: blog limit option (see Flagged ambiguities)

**Blog Watch**:
A member's subscription to a blog, which alerts them when the blog gets a new post.
_Avoid_: follow, subscribe

**Watcher**:
A member with a Blog Watch on a blog.

### Blog posts

**Blog Post**:
A single article inside a blog.
_Avoid_: entry, article, post (alone; "post" also means a forum post)

**Blog Post State**:
Where a blog post is in its lifecycle: `visible`, `scheduled`, `draft`, `moderated`, or `deleted`.

**Scheduled post**:
A blog post in the `scheduled` state whose deferred job makes it visible at `scheduled_post_date_time`.

**Draft**:
A blog post in the `draft` state. Nothing publishes it automatically; the author has to publish it.

**Soft delete**:
Moving a blog or blog post to the `deleted` state, which leaves it restorable and recorded in the deletion log.

**Hard delete**:
Permanently removing a blog or blog post row, behind separate moderator permissions.

**Read time**:
Estimated minutes to read a blog post, computed from word count at 225 words per minute.

### Comments

**Discussion Thread**:
The forum thread linked to a blog post through `discussion_thread_id`, whose replies are the post's comments.
_Avoid_: comment thread, blog thread

**Comment**:
A reply in a blog post's Discussion Thread. It is a regular XenForo post, not a separate entity.

**Comment forum**:
The forum, set by the `taylorjBlogsBlogPostForum` option, where Discussion Threads are created.

**`blogPost` thread type**:
The custom thread type given to every Discussion Thread. Users can't create threads of this type or convert threads to it.

**Deletion thread action**:
What happens to a Discussion Thread when its blog post is deleted (close, retitle, add a post), set by `taylorjBlogsBlogPostDeleteThreadAction`.

### Derived data

**Blog post count**:
The denormalized number of visible posts, stored on the blog (`blog_post_count`) and the member (`xf_user.taylorj_blogs_blog_post_count`).

**Blog count**:
The denormalized number of blogs a member owns (`xf_user.taylorj_blogs_blog_count`), checked against the blog creation limit.

**View count**:
How many times a blog post has been viewed. Views are buffered in a MEMORY table and periodically written to `view_count`.

**Similar blog posts**:
A cached list of related blog post IDs for a blog post, fetched from XenForo Enhanced Search (XFES).

## Relationships

- An **Author** owns zero or more **Blogs**, up to the **Blog creation limit**.
- A **Blog** contains zero or more **Blog Posts**. Each **Blog Post** belongs to exactly one **Blog**.
- A **Blog Post** has at most one **Discussion Thread**, which lives in the **Comment forum** and has the **`blogPost` thread type**.
- A **Blog** has zero or more **Watchers**, each through one **Blog Watch**.
- A **Blog Post** has at most one **Similar blog posts** cache row.

## Example dialogue

> **Dev:** "When an **Author** schedules a **Blog Post**, do **Watchers** get alerted right away?"
> **Domain expert:** "No. **Watchers** are only alerted when a post is created as `visible`. A **Scheduled post** gets its **Discussion Thread** when its job publishes it, and today that publish sends no alerts."
> **Dev:** "And if a moderator **soft deletes** it after that?"
> **Domain expert:** "The post moves to `deleted`, and the **Deletion thread action** decides what happens to the **Discussion Thread**. The **Comments** stay in the forum."

## Flagged ambiguities

- "comments" means replies in the **Discussion Thread**. The add-on has no comment entity or table.
- "blogs" is the current route prefix and index. The old "userblogs" routes and navigation were removed in 1.7.0, so don't reintroduce that name.
- "delete" means **Soft delete** unless the context says hard delete.
- "blog limit" can mean two things. The `taylorjBlogsBlogLimit` option exists in `_data/options.xml`, but no code reads it. The enforced limit is the `blogCreationLimit` permission (`Pub/Controller/Blogs.php`).
- "publish" happens on two paths with different side effects. Creating a post as `visible` alerts **Watchers** (`Pub/Controller/Blog.php`). Publishing a **Scheduled post** (`Service/BlogPost/Edit::handlePostStateChange`) creates the **Discussion Thread** but sends no alerts. Not yet confirmed whether that's intended.
- The Similar Blog Posts widget was previously described as "removed in v1.5.1 due to server errors", but its definition (`taylorj_blo_similar_posts`) and cron are still registered. Treat it as shipped but XFES-dependent until that's confirmed. See [ADR 0004](docs/adr/0004-similar-posts-cached-from-xfes.md).
- `taylorj_blogs_list_macros` (the `blog` and `blogPost` macros) and the `blog_icon()` template function depend on XFRM's `xfrm.less`, `avatar--resourceIconDefault`, and `xfrm_resource_icon`, but `addon.json` doesn't require XFRM. Without XFRM, those lists (author view, widgets, What's New) lose their styling. The **Blog index** doesn't use these macros.
