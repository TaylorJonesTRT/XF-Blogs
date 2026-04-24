<?php

namespace TaylorJ\Blogs\Pub\Controller;

use XF\Pub\Controller\AbstractWhatsNewFindType;

class WhatsNewBlogPosts extends AbstractWhatsNewFindType
{
    protected function getContentType()
    {
        return 'taylorj_blogs_blog_post';
    }
}
