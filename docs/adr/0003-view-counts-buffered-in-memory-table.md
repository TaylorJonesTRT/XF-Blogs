# Blog post views are buffered in a MEMORY table

Each view inserts or increments a row in `xf_taylorj_blogs_blog_post_view`, a MEMORY-engine table (`Repository/BlogPost::logThreadView`). Every 30 minutes the `userblogs` cron adds those totals to `blog_post.view_count` and empties the buffer (`batchUpdateThreadViews`). This copies XenForo's own thread-view pattern so page views never write to the main posts table. In exchange, we accept losing any unflushed views if the database restarts.
