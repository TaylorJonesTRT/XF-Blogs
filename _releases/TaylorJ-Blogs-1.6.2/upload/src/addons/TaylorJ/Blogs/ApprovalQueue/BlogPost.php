<?php

namespace TaylorJ\Blogs\ApprovalQueue;

use XF\ApprovalQueue\AbstractHandler;
use XF\Mvc\Entity\Entity;
use TaylorJ\Blogs\Service\BlogPost\Approve;

class BlogPost extends AbstractHandler
{
    protected function canActionContent(Entity $content, &$error = null)
    {
        /** @var $content \TaylorJ\Blogs\Entity\BlogPost */
        return $content->canApproveUnapprove($error);
    }

    public function getEntityWith()
    {
        $visitor = \XF::visitor();

        return ['Blog', 'User'];
    }

    public function actionApprove(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
    {
        /** @var Approve $approver */
        $approver = \XF::service('TaylorJ\Blogs:BlogPost\Approve', $blogPost);
        $approver->setNotifyRunTime(1); // may be a lot happening
        $approver->approve();
    }

    public function actionDelete(\TaylorJ\Blogs\Entity\BlogPost $blogPost)
    {
        $this->quickUpdate($blogPost, 'blog_post_state', 'deleted');
    }
}
