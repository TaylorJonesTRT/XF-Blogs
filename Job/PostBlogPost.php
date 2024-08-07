<?php
namespace TaylorJ\Blogs\Job;

use XF\Job\AbstractJob;

class PostBlogPost extends AbstractJob
{
	public function run($maxRunTime)
	{
        $app = \XF::app();
        
        $blog_post_id = $this->data['blog_post_id'];
        try
        {                        
            $blogPost = \XF::finder('TaylorJ\Blogs:BlogPost')->where('blog_post_id', $blog_post_id)->fetchOne();
            if($blogPost)
            {                
                if($blogPost->blog_post_date <= \XF::$time)
                {                    
                }
            }
        } 
        catch (\Exception $ex) 
        {
            \XF::logError('[TaylorJ\Blogs] --> Could not post blog with'.$blog_post_id);
        }
		finally
        {            
            return $this->complete();
        }
    }

    public function getStatusMessage()
	{
        return 'Posting blog...';
    }

    public function canCancel()
    {
        return false;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}