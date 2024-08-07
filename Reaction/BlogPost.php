<?php

namespace TaylorJ\Blogs\Reaction;

use XF\MVC\Entity\Entity;
use XF\Reaction\AbstractHandler;

class BlogPost extends AbstractHandler
{
    public function reactionsCounted(Entity $entity)
    {
        return ($entity->blog_post_state === 'visible');
    }
    
    public function getEntityWith()
    {
        return ['TaylorJ\Blogs:BlogPost', 'TaylorJ\Blogs:BlogPost.User'];
    }
}