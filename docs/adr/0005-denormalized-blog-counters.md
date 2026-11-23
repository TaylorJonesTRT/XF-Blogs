# Blog and post counts are denormalized

Counts are stored as columns instead of being computed: `blog.blog_post_count`, plus `taylorj_blogs_blog_count` and `taylorj_blogs_blog_post_count` added to `xf_user` (`Setup.php`, entity structure through `Listener::userEntityStructure`). This keeps listings, member stats, user searcher sorting, and the blog creation limit check cheap. Services adjust the counts as state changes (`Utils.php` count helpers). Drift is fixed by the daily `taylorjBlogsPostCount` cron (`Cron/Blog.php`) and the `UserBlogPostCountTotal` rebuild job.
