<?php

namespace TaylorJ\Blogs\Option;

use XF\Entity\Option;
use XF\Repository\PermissionCombinationRepository;
use XF\Repository\ForumRepository;

use function array_key_exists, is_array;

class ForumChoiceAction extends AbstractOption
{
	public static function renderOption(Option $option, array $htmlParams)
	{
		/** @var ForumRepository $forumRepo */
		$forumRepo = \XF::repository('XF:Forum');

        $forumOptions = $forumRepo->getForumOptionsData(false, 'discussion');

		return static::getTemplate('admin:taylorj_blogs_option_template_forumChoiceAction', $option, $htmlParams, [
			'forumOptions' => $forumOptions,
            'option' => $option
		]);
	}

	// public static function verifyOption(int &$value, Option $option)
	// {
	// 	$value['permissionCombinationId'] = $combination->permission_combination_id;

	// 	return true;
	// }
}
