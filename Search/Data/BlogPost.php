<?php

namespace TaylorJ\Blogs\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class BlogPost extends \XF\Search\Data\AbstractData
{
	public function getIndexData(Entity $entity)
	{
		$index = IndexRecord::create('taylorj_blogs_blog_post', $entity->blog_post_id, [
			'title' => $entity->blog_post_title,
			'message' => $entity->blog_post_content,
			'date' => $entity->blog_post_date,
			'metadata' => $this->getMetaData($entity)
		]);

		return $index;
	}

	protected function getMetaData(\TaylorJ\Blogs\Entity\BlogPost $entity)
	{
		$blog = $entity->Blog;

		$metadata = [
			'blog' => $blog->blog_id,
			'blogPost' => $entity->blog_post_id
		];

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('blog', MetadataStructure::INT);
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
			'options' => $options
		];
	}

	public function getSearchableContentTypes()
	{
		return ['taylorj_blogs_blog_page'];
	}

	public function getSectionContext()
	{
		return 'TaylorJ\Blogs';
	}

	public function getGroupByType()
	{
		return 'taylorj_blogs_blog_post';
	}
}