<?php

namespace TaylorJ\Blogs\Search;

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
		return [];
	}

	public function setupMetadataStructure(MetadataStructure $structure)
	{
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

	// public function getSearchFormTab()
	// {
	// 	$visitor = \XF::visitor();
	// 	if (!$visitor->hasPermission('EWRcarta', 'viewWiki'))
	// 	{
	// 		return null;
	// 	}

	// 	return [
	// 		'title' => \XF::phrase('EWRcarta_search_wiki'),
	// 		'order' => 200
	// 	];
	// }

	public function getSectionContext()
	{
		return 'TaylorJ\Blogs';
	}

	public function getGroupByType()
	{
		return 'taylorj_blogs_blog_post';
	}
}