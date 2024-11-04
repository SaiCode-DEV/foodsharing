<?php

namespace Foodsharing\Modules\Blog;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\RestApi\Models\Blog\BlogPostData;

class BlogTransactions
{
    public function __construct(
        private readonly BlogGateway $blogGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly Session $session,
    ) {
    }

    /**
     * Adds a blog post and returns the created post's id. This also makes sure that the post's picture, if any, is
     * properly tagged.
     */
    public function addBlogPost(BlogPostData $post): int
    {
        $postId = $this->blogGateway->addBlogPost($this->session->id(), $post);

        if (!empty($post->picture)) {
            // cut the `/api/uploads/` in front of the UUID
            $uuid = substr($post->picture, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $postId);
        }

        return $postId;
    }

    public function editBlogPost(int $authorId, BlogPostData $post): void
    {
        $this->blogGateway->update_blog_entry($authorId, $post);

        if (!empty($post->picture)) {
            // cut the `/api/uploads/` in front of the UUID
            $uuid = substr($post->picture, 13);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $post->id);
        }
    }
}
