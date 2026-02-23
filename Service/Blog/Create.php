<?php

namespace TaylorJ\Blogs\Service\Blog;

use TaylorJ\Blogs\Entity\Blog;
use XF\Service\AbstractService;
use XF\Service\Thread\Creator;

use TaylorJ\Blogs\Utils as Utils;

class Create extends AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/**
	 * @var \TaylorJ\Blogs\Entity\Blog
	 */
	public $blog;

	/**
	 * @var TaylorJ\Blogs\Entity\Blog 
	 */

	public function __construct(\XF\App $app, Blog $blog)
	{
		parent::__construct($app);
		$this->blog = $blog;
		$this->initialize();
	}

	protected function initialize() {}

	public function setTitle($title)
	{
		$this->blog->blog_title = $title;
	}

	public function setDescription($description)
	{
		$this->blog->blog_description = $description;
	}

	public function setState()
	{
		$this->blog->blog_state = $this->blog->getNewContentState();
	}

	public function setHasBlogHeader()
	{
		$this->blog->blog_has_header = 1;
	}

	public function finalSteps() {}

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
}
