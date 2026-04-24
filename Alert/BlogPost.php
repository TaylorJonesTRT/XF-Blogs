<?php

namespace TaylorJ\Blogs\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class BlogPost extends AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        /** @var \TaylorJ\Blogs\Entity\BlogPost $entity */
        return $entity->canView();
    }

    public function getOptOutActions()
    {
        return [
            'edit',
        ];
    }

    public function getOptOutDisplayOrder()
    {
        return 1000;
    }
}
