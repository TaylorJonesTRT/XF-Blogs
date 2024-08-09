<?php

namespace TaylorJ\Blogs\Service\BlogPost;

use TaylorJ\Blogs\Entity\Blog;

use TaylorJ\Blogs\Utils as Utils;

class Create extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

    /**
	 * @var \TaylorJ\Blogs\Entity\BlogPost
	 */
	protected $blogPost;

	/**
	 * @var TaylorJ\Blogs\Entity\Blog 
	 */
	protected $update;

	/**
	 * @var Blog 
	 */
	protected $blog;

    public function __construct(\XF\App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
		$this->initialize();
	}

    protected function initialize()
	{
		$blogPost = $this->blog->getNewBlogPost();
		$this->blogPost = $blogPost;
	}

    public function setTitle($title)
	{
		$this->blogPost->blog_post_title = $title;
	}

    public function setContent($content)
	{
		$this->blogPost->blog_post_content = $content;
	}

    public function setScheduledPostDateTime($scheduledPostTime)
	{
		$tz = new \DateTimeZone(\XF::visitor()->timezone);
		
		$postDate = $scheduledPostTime['dd'];
		$postHour = $scheduledPostTime['hh'];
		$postMinute = $scheduledPostTime['mm'];
        
        if(!$scheduledPostTime['blog_post_schedule'])
        {
            $dateTime = new \DateTime("$postDate $postHour:$postMinute", $tz);
		
		    $this->blogPost->scheduled_post_date_time = $dateTime->format('U');
            $this->blogPost->blog_post_state = 'scheduled';
        }		
        else
        {
            $this->blogPost->scheduled_post_date_time = 0;
            $this->blogPost->blog_post_state = 'visible';
        }
	}

    public function finalSteps()
    {
        if ($this->blogPost->blog_post_state === 'scheduled')
        {
            $this->insertJob();
        }
    }

    protected function _validate()
	{        
        $this->blogPost->preSave();
		$errors = $this->blogPost->getErrors();

        return $errors;
    }

    protected function _save()
    {        
        $this->blogPost->save(true, false);

        return $this->blogPost;
    }

    public function insertJob()
    {
        $jobid = 'taylorjblogs_scheduledpost_'.$this->blogPost->blog_post_id.'_'.\XF::$time;
        $app = \XF::app();
        $app->jobManager()->enqueueLater($jobid, $this->blogPost->scheduled_post_date_time, 'TaylorJ\Blogs:PostBlogPost', ['blog_post_id' => $this->blogPost->blog_post_id]);
    }

	public function sendNotifications()
	{
		if ($this->blog->isVisible())
		{
			/** @var \TaylorJ\Blogs\Service\BlogPost\Notify $notifier */
			$notifier = $this->service('TaylorJ\Blogs:Blog\Notify', $this->blog, 'newBlogPost');
			$notifier->notifyAndEnqueue();
		}
	}
}