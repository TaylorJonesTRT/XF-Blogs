# Scheduled posts publish through per-post deferred jobs

Saving a post in the `scheduled` state enqueues a unique deferred job (`enqueueLater`, id `taylorjblogs_scheduledpost_{blog_post_id}`) set to run at `scheduled_post_date_time` (`Service/BlogPost/Create::insertJob`). There is no cron that polls for due posts. When the job runs, `Job/PostBlogPost.php` checks the post date again and calls `Edit::handlePostStateChange()`, which makes the post visible and creates its Discussion Thread. Rescheduling updates the existing job's `trigger_date` by its unique key (`Repository/BlogPost::updateJob`), and `removeJob` cancels it.

## Consequences

- Publishing depends on XenForo's job runner, so a site with little traffic or no server-side job runner may publish late.
- This publish path doesn't send Blog Watch alerts. Only posts created as `visible` alert watchers (see Flagged ambiguities in `CONTEXT.md`).
