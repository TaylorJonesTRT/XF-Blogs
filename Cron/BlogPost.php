<?php

namespace TaylorJ\Blogs\Cron;

class BlogPost
{
    public static function runViewUPdate()
    {
        $app = \XF::app();

        /** @var \TaylorJ\Blogs\Repository\BlogPost $blogPostRepo */
        $blogPostRepo = $app->repository('TaylorJ\Blogs:BlogPost');
        $blogPostRepo->batchUpdateThreadViews();
        
        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $app->repository('XF:Attachment');
        $attachmentRepo->batchUpdateAttachmentViews();
    }
}