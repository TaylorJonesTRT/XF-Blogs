<?php

namespace TaylorJ\Blogs\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class Blog extends AbstractData
{
	public function getIndexData(Entity $entity)
	{
		$index = IndexRecord::create('taylorj_blogs_blog', $entity->blog_id, [
			'title' => $entity->blog_title,
			'description' => $entity->blog_description,
			'lastPostDate' => $entity->blog_last_post_date,
			'metadata' => $this->getMetaData($entity),
		]);

		return $index;
	}

	protected function getMetaData(\TaylorJ\Blogs\Entity\Blog $entity)
	{

		$metadata = [
			'blog' => $entity->blog_id,
		];

		return $metadata;
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('blog', MetadataStructure::INT);
	}

	public function getResultDate(Entity $entity)
	{
		return $entity->blog_creation_date;
	}

	public function getTemplateData(Entity $entity, array $options = [])
	{
		return [
			'blog' => $entity,
			'options' => $options,
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
		return 'taylorj_blogs_blog';
	}
}
