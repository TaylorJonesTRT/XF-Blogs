<?php

namespace TaylorJ\Blogs;

use XF\Container;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Searcher\User;
use XF\Template\Templater;

class Listener
{
    public static function userEntityStructure(Manager $em, Structure &$structure)
    {
        $structure->columns['taylorj_blogs_blog_count'] = ['type' => Entity::INT, 'default' => 0];
        $structure->columns['taylorj_blogs_blog_post_count'] = ['type' => Entity::INT, 'default' => 0];
    }

    public static function userSearcherOrders(User $userSearcher, array &$sortOrders)
    {
        $sortOrders['taylorj_blogs_blog_post_count'] = \XF::phrase('taylorj_blogs_blog_post_count');
    }

    public static function memberStatResultPrepare($order, array &$cacheResults)
    {
        if ($order == 'taylorj_blogs_blog_post_count')
        {
            $cacheResults = array_map(function ($value)
            {
                return \XF::language()->numberFormat($value);
            }, $cacheResults);
        }
    }

    public static function templaterSetup(Container $container, Templater &$templater)
    {
        /** @var TemplaterSetup $templaterSetup */
        $class = \XF::extendClass('TaylorJ\Blogs\Template\TemplaterSetup');
        $templaterSetup = new $class();

        $templater->addFunction('blog_icon', [$templaterSetup, 'fnBlogIcon']);
    }
}
