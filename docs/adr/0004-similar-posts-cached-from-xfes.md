# Similar blog posts are cached from XFES

Similar posts come from XenForo Enhanced Search and are cached per post in `xf_taylorj_blogs_blog_post_similar`, so XFES isn't queried on every page view. A cache row is flagged `pending_rebuild` when it's missing or older than 14 days (`Entity/BlogPostSimilar::isRebuildRequired`), and when the widget hits an error. The hourly `taylorjBlogsSimilarPosts` cron enqueues `Job/SimilarBlogPosts`, which rebuilds flagged rows. Without XFES the job exits immediately, so the feature does nothing instead of failing.

## Status

The widget was previously described as "removed in v1.5.1 due to server errors". However, the widget definition `taylorj_blo_similar_posts` and the cron are still registered in `_data/`. Confirm which is true and update this ADR.
