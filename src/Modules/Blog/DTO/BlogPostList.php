<?php

namespace Foodsharing\Modules\Blog\DTO;

/**
 * Combines a page of the list of blog posts with the total number of posts. This is necessary for pagination in the
 * frontend.
 */
class BlogPostList
{
    /**
     * @var BlogPost[]
     */
    public array $blogPosts;

    public int $totalPosts;

    /**
     * @param BlogPost[] $blogPosts
     */
    public static function create(array $blogPosts, int $totalPosts): BlogPostList
    {
        $list = new BlogPostList();
        $list->blogPosts = $blogPosts;
        $list->totalPosts = $totalPosts;

        return $list;
    }
}
