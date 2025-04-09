\[TaylorJ\] Blogs for XenForo 2.3.0+
====================================

Description
-----------

A user blogging system to allow your users to express themselves

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
| Blog post deletion thread action | When a blog post is deleted, take this action with any automatically created thread. |
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
- Can undelete own blog post
- Can view deleted blog posts
- Can view moderated blog posts
- Can undelete owned blog(s)

#### \[TaylorJ\] Blogs Moderator Permissions

- Can edit any blog
- Can delete any blog
- Can edit any blog post
- Can delete any blog post
- Can tag any blog post
- Can manage any blog posts tags
- Use inline moderation on resources
- Can view moderated content
- Can hard delete any blog post
- Can undelete any blog post
- Can hard delete any blog
- Can undelete any blog

Widget Positions
----------------

| Position | Description |
|---|---|
| \[TaylorJ\] Blog Post View:Below (`taylorj_blogs_blog_post_below_post`) | Position in the bottom of the taylorj\_blogs\_blog\_post\_view template. Widget templates rendered in this position can use the current blog post entity in the `{$context.blogPost}` param. |

Widget Definitions
------------------

| Definition | Description |
|---|---|
| \[TaylorJ\] Latest Blog Posts (`taylorj_latest_blog_posts`) | Displays latest blog posts |
| \[TaylorJ\] Other blog posts by author (`taylorj_other_blog_posts`) | Show other random blog posts by the same author/user. |

Cron Entries
------------

| Name | Run on... | Run at hours | Run at minutes |
|---|---|---|---|
| Update the blog post count to reflect visible posts | Any day of the month | 12AM | 0 |
| Update Blog Post View Counter | Any day of the month | Any | 30 |