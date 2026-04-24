<?php

namespace TaylorJ\Blogs\Template;

use XF\Template\Templater;
use TaylorJ\Blogs\Entity\Blog;

class TemplaterSetup
{
    public function fnBlogIcon($templater, &$escape, Blog $blog, $size = 'm', $href = '', $attributes = [])
    {
        $escape = false;

        $size = preg_replace('#[^a-zA-Z0-9_-]#s', '', $size);

        if ($href) {
            $tag = 'a';
            $hrefAttr = 'href="' . htmlspecialchars($href) . '"';
        } else {
            $tag = 'span';
            $hrefAttr = '';
        }

        /** @var Templater $templater */
        $attributesString = $templater->getAttributesAsString($attributes);

        if (!$blog->blog_header_image) {
            return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size} avatar--resourceIconDefault\"><span></span><span class=\"u-srOnly\">" . \XF::phrase('xfrm_resource_icon') . "</span></{$tag}>";
        } else {
            $src = $blog->getIconUrl($size);

            return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size}\"{$attributesString}>"
                . '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($blog->blog_title) . '" loading="lazy" />'
                . "</{$tag}>";
        }
    }
}
