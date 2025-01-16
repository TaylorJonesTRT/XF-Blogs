<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use XF\App;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use XF\Util\File;

class Edit extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var BlogPost
	 */
	protected $blog;

	public function __construct(App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
	}

	public function setHasBlogHeader()
	{
		$this->blog->blog_has_header = 1;
	}

	public function setTitle($title)
	{
		$this->blog->blog_title = $title;
	}

	public function setDescription($description)
	{
		$this->blog->blog_description = $description;
	}

	protected function finalSetup()
	{
	}

	protected function _validate()
	{
		$this->blog->preSave();
		$errors = $this->blog->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$blog = $this->blog;

		$blog->save(true, false);

		return $blog;
	}

	public function finalSteps()
	{
	}

	protected function deleteBlogHeaderImageFiles()
	{
		if ($this->blog->blog_has_header)
		{
			File::deleteFromAbstractedPath($this->blog->getBlogHeaderImage);
		}
	}

}
