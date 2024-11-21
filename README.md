\[TaylorJ\] Blogs - Premium for XenForo 2.3.0+
==============================================

Description
-----------

A premium user blogging system to allow your users to express themselves

Options
-------

#### \[TaylorJ\] Blogs - Options

| Name | Description |
|---|---|
| Minimum tag amount | How many tags must a blog post have to be able to be posted |
| Blog Posts per page | How many blog posts to show per page on the blog view |
| Moderate blog creation | Force all new blog creations into the approval queue |
| Blog creation limit | Limit your users to how many blogs they can own/create |
| Moderate blog post creation | Force all new visible blog posts to the approval queue |
| Blog post comments | When turned on blogs will be created with comment threads in forum specified below, when off blogs will not have comments |
| Forum for comment thread creation | Pick a forum that will be used to automatically create threads in for blog posts to be used as comments. |
| Show other posts by blog post author | If turned on this will show a random list of other blog posts by that author in any blog. |
| Blogs per page | How many blogs to display per page on the blogs index page |

Permissions
-----------

#### \[TaylorJ\] Blogs permissions

- Can view own blog
- Can view blogs
- Can view any blog
- Can edit own blog
- Can delete own blog
- Can create a new blog
- Can make a blog post
- Can edit own blog post
- Can delete own blog post
- Can tag own blog post
- Can submit blog posts without approval
- Blog limit

#### \[TaylorJ\] Blogs Moderator Permissions

- Can edit any blog
- Can delete any blog
- Can edit any blog post
- Can delete any blog post
- Can tag any blog post
- Can manage any blog posts tags
- Use inline moderation on resources
- Can view moderated content

Widget Positions
----------------

| Position | Description |
|---|---|
| Blog post view: Below post (`taylorj_blogs_blog_post_below_post`) | A position in the main content area of the blog post view, below the messages. Widget templates rendered in this position can use the current thread entity in the `{$context.blogPost}` param. |

Widget Definitions
------------------

| Definition | Description |
|---|---|
| \[TaylorJ\] Blogs: Similar blog posts (`taylorj_blogs_similarpost`) | Displays a block containing a list of X blog posts which are similar to the current one being viewed. This widget will only work in "blog post view" positions. |
| Latest Blog Posts (`taylorj_latest_blog_posts`) | Displays latest blog posts |

Cron Entries
------------

| Name | Run on... | Run at hours | Run at minutes |
|---|---|---|---|
| \[TaylorJ\] Blogs: Update similar blog posts caches | Any day of the month | Any | 32 |
| Update Blog Post View Counter | Any day of the month | Any | 30 |