<?php

namespace TaylorJ\Blogs\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Blog extends AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        /** @var \TaylorJ\Blogs\Entity\Blog $entity */
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
