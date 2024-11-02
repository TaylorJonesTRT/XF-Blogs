<?php

namespace TaylorJ\Blogs\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\Data\AutoCompletableInterface;
use XF\Search\Data\AutoCompletableTrait;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class BlogPost extends AbstractData implements AutoCompletableInterface
{
	use AutoCompletableTrait;

	public function getIndexData(Entity $entity)
	{
		$index = IndexRecord::create('taylorj_blogs_blog_post', $entity->blog_post_id, [
			'title' => $entity->blog_post_title,
			'message' => $entity->blog_post_content,
			'date' => $entity->blog_post_date,
			'metadata' => $this->getMetaData($entity),
		]);

		return $index;
	}

	protected function getMetaData(\TaylorJ\Blogs\Entity\BlogPost $entity)
	{
		$blog = $entity->Blog;

		$metadata = [
			'blogPost' => $entity->blog_post_id,
		];

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('blogPost', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->blog_post_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'blogPost' => $entity,
			'options' => $options,
		];
	}

	public function getSearchableContentTypes()
	{
		return ['taylorj_blogs_blog_post'];
	}

	public function getSearchFormTab()
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewBlogs') || !$visitor->canViewBlogs())
		{
			return null;
		}

		return [
			'title' => \XF::phrase('taylorj_blogs_search_blog_posts'),
			'order' => 3000,
		];
	}

	public function getSectionContext()
	{
		return 'TaylorJ\Blogs';
	}

	public function getGroupByType()
	{
		return 'taylorj_blogs_blog_post';
	}

	public function getAutoCompleteResult(
		Entity $entity,
		array $options = []
	): ?array
	{
		return $this->getSimpleAutoCompleteResult(
			$entity->blog_post_title,
			$entity->getContentUrl(),
			$entity->blog_post_content,
			$entity->User
		);
	}
}
