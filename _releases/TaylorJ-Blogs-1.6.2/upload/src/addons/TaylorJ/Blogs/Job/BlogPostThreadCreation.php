<?php

namespace TaylorJ\Blogs\Job;

use TaylorJ\Blogs\Entity\BlogPost;
use XF\Job\AbstractRebuildJob;


class BlogPostThreadCreation extends AbstractRebuildJob
{
    protected function getNextIds($start, $batch)
    {
        $db = $this->app->db();

        return $db->fetchAllColumn(
            $db->limit("SELECT `blog_post_id` FROM `xf_taylorj_blogs_blog_post` WHERE `blog_post_id` > ? ORDER BY `blog_post_id`", $batch),
            $start
        );
    }


    protected function rebuildById($id)
    {
        /** @var BlogPost $blogPost **/
        $blogPost = $this->app->em()->find('TaylorJ\Blogs:BlogPost', $id);
        if ($blogPost) {
            $blogPost->createCommentThreadsForOldBlogs();
            $blogPost->save();
        }
    }

    protected function getStatusType()
    {
        return \XF::phrase('taylorj_blogs_blog_post_thread_creation_job');
    }
}
