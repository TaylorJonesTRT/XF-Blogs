<?php

namespace TaylorJ\UserBlogs\Cron;

class BlogPost
{
    public static function runViewUPdate()
    {
        $app = \XF::app();

        /** @var \TaylorJ\UserBlogs\Repository\BlogPost $blogPostRepo */
        $blogPostRepo = $app->repository('TaylorJ\UserBlogs:BlogPost');
        $blogPostRepo->batchUpdateThreadViews();
        
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $app->repository('XF:Attachment');
        $attachmentRepo->batchUpdateAttachmentViews();
    }
}