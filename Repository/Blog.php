<?php

namespace TaylorJ\Blogs\Repository;

use TaylorJ\Blogs\Entity\Blog as BlogEntity;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Util\File;

class Blog extends Repository
{
	public function setBlogHeaderImagePath($blog_id, $upload)
	{
		$upload->requireImage();

		if (!$upload->isValid($errors))
		{
			throw new PrintableException(reset($errors));
		}

		$dataDir = \XF::app()->config('externalDataPath');
		$dataDir .= "://taylorj_blogs/blog_header_images/" . $blog_id . ".jpg";

		try
		{
			$image = \XF::app()->imageManager->imageFromFile($upload->getTempFile());

			$tempFile = File::getTempFile();
			if ($tempFile && $image->save($tempFile))
			{
				$output = $tempFile;
			}
			unset($image);

			File::copyFileToAbstractedPath($output, $dataDir);
		}
		catch (Exception $e)
		{
			throw new PrintableException(\XF::phrase('unexpected_error_occurred'));
		}
	}

	public function deleteBlogHeaderImage(BlogEntity $blog)
	{
		File::deleteFromAbstractedPath($blog->getAbstractedHeaderImagePath());
	}

	public function findBlogsByUser($userId)
	{
		$blogFinder = $this->finder('TaylorJ\Blogs:Blog')
			->where('user_id', $userId)
			->setDefaultOrder('blog_last_post_date', 'desc');

		return $blogFinder;
	}

	public function batchUpdateBlogPostCounts()
	{
		$blogs = $this->finder('TaylorJ\Blogs:Blog')->fetch();

		/** @var BlogEntity $blog */
		foreach ($blogs AS $blog)
		{
			$blog->fastUpdate('blog_post_count', $blog->getBlogPostCount());
		}
	}
}
