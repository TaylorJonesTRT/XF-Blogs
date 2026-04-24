<?php

namespace TaylorJ\Blogs\Job;

use TaylorJ\Blogs\Repository\BlogPost as BlogPostRepo;
use XF\Job\AbstractRebuildJob;
use XF\Job\JobResult;
use XF\Phrase;

class SimilarBlogPosts extends AbstractRebuildJob
{
    protected $failureLogged = false;

    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     */
    public function run($maxRunTime)
    {
        $addOns = \XF::app()->container('addon.cache');
        $hasXFES = array_key_exists('XFES', $addOns);

        if (!$hasXFES)
        {
            return $this->complete();
        }

        return parent::run($maxRunTime);
    }

    /**
     * @param int $start
     * @param int $batch
     *
     * @return int[]
     */
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn(
            $db->limit(
                'SELECT blog_post_id
					FROM xf_taylorj_blogs_blog_post_similar
					WHERE blog_post_id > ? AND pending_rebuild = 1
					ORDER BY blog_post_id',
                $batch
            ),
            $start
        );
    }

    /**
     * @param int $id
     */
    protected function rebuildById($id)
    {
        $blogPost = $this->app->find('TaylorJ\Blogs:BlogPost', $id);
        if (!$blogPost)
        {
            return;
        }

        /** @var BlogPostRepo $blogPostRepo */
        $blogPostRepo = $this->app->repository('TaylorJ\Blogs:BlogPost');

        try
        {
            $blogPostRepo->rebuildSimilarBlogPostsCache($blogPost);
        }
        catch (\Exception $e)
        {
            if (!$this->failureLogged)
            {
                \XF::logException($e, false, "Similar blog post cache rebuild failure: ");
                $this->failureLogged = true;
            }
        }
    }

    /**
     * @return Phrase
     */
    protected function getStatusType()
    {
        return \XF::phrase('taylorj_blogs_blog_post_similar_rebuild');
    }
}
