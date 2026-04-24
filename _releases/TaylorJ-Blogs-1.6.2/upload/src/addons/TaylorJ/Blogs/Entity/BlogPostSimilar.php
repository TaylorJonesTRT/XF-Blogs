<?php

namespace TaylorJ\Blogs\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $blog_post_id
 * @property int $last_update_date
 * @property bool $pending_rebuild
 * @property array $similar_blog_post_ids
 */
class BlogPostSimilar extends Entity
{
	/**
	 * @var int
	 */
	public const MAX_RESULTS = 100;

	public function isRebuildRequired()
	{
		return ($this->last_update_date < \XF::$time - 14 * 86400);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_taylorj_blogs_blog_post_similar';
		$structure->shortName = 'TaylorJ\Blogs:BlogPostSimilar';
		$structure->primaryKey = 'blog_post_id';
		$structure->columns = [
			'blog_post_id' => [
				'type' => self::UINT,
				'required' => true,
			],
			'last_update_date' => [
				'type' => self::UINT,
				'default' => 0,
			],
			'pending_rebuild' => [
				'type' => self::BOOL,
				'default' => false,
			],
			'similar_blog_post_ids' => [
				'type' => self::LIST_COMMA,
				'default' => [],
				'list' => [
					'type' => 'posint',
					'unique' => true,
				],
			],
		];
		$structure->getters = [];
		$structure->relations = [];

		return $structure;
	}
}
