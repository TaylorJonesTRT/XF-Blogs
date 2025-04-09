<?php

namespace TaylorJ\Blogs\Cron;

use XF\Repository\Attachment;

class BlogPost
{
	public static function runViewUpdate()
	{
		$app = \XF::app();

		/** @var \TaylorJ\Blogs\Repository\BlogPost $blogPostRepo */
		$blogPostRepo = $app->repository('TaylorJ\Blogs:BlogPost');
		$blogPostRepo->batchUpdateThreadViews();

		/** @var Attachment $attachmentRepo */
		$attachmentRepo = $app->repository('XF:Attachment');
		$attachmentRepo->batchUpdateAttachmentViews();
	}
}
