<?php

namespace TaylorJ\Blogs\Report;

use XF\Entity\Report;
use XF\Mvc\Entity\Entity;
use XF\Report\AbstractHandler;
use TaylorJ\Blogs\Entity\BlogPost;
use TaylorJ\Blogs\XF\Entity\User;

class BlogPostHandler extends AbstractHandler
{
    protected function canViewContent(Report $report)
    {
        return \XF::visitor()->hasPermission('taylorjBlogs', 'viewBlogs');
    }

    protected function canActionContent(Report $report)
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();

        return ($visitor->hasPermission('taylorjBlogs', 'canEditAny') || $visitor->hasPermission('taylorjBlogs', 'canDeleteAny'));
    }

    public function setupReportEntityContent(Report $report, Entity $content)
    {
        $blogPostTitle = $content->blog_post_title;

        /** @var BlogPost $content */
        $report->content_user_id = $content->user_id;
        $report->content_info = [
            'blog' => [
                'blog_id' => $content->blog_id,
                'blog_title' => $content->Blog->blog_title,
            ],
            'blogPost' => [
                'blog_post_id' => $content->blog_post_id,
                'blog_post_title' => $blogPostTitle,
                'blog_post_message' => $content->blog_post_content,
                'user_id' => $content->user_id,
                'username' => $content->User->username
            ],
        ];
    }

    public function getContentTitle(Report $report)
    {
        return \XF::phrase('taylorj_blog_post_in_blog_x', [
            'title' => \XF::app()->stringFormatter()->censorText($report->content_info['blog']['blog_title']),
        ]);
    }

    public function getContentMessage(Report $report)
    {
        return $report->content_info['blog_post_content'];
    }

    public function getContentLink(Report $report)
    {
        if (!empty($report->content_info['blog_post_id'])) {
            $linkData = $report->content_info;
        } else {
            $linkData = ['post_id' => $report->content_id];
        }

        return \XF::app()->router('public')->buildLink('canonical:blogs/post', $linkData);
    }

    /*public function getEntityWith()*/
    /*{*/
    /*    return ['TaylorJ\Blogs:Blog', 'User'];*/
    /*}*/
}
