<?php

namespace Foodsharing\Modules\Blog;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Blog\DTO\BlogPost;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\RestApi\Models\Blog\BlogPostData;
use Foodsharing\Utility\Sanitizer;

class BlogTransactions
{
    public function __construct(
        private readonly BellGateway $bellGateway,
        private readonly BlogGateway $blogGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly Sanitizer $sanitizer,
        private readonly Session $session,
    ) {
    }

    /**
     * Adds a blog post and returns the created post's id. This also makes sure that the post's picture, if any, is
     * properly tagged.
     */
    public function addBlogPost(BlogPostData $post): int
    {
        $post->picture = $this->uploadsTransactions->fixUUIDForWriting($post->picture);
        $postId = $this->blogGateway->addBlogPost($this->session->id(), $post);

        if (!empty($post->picture)) {
            // cut the `/api/uploads/` in front of the UUID
            $uuid = $this->uploadsTransactions->getUUID($post->picture);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $postId);
        }

        // Create a new bell
        $foodsaver = [];
        $orga = $this->foodsaverGateway->getOrgaTeam();
        $ambassadors = $this->foodsaverGateway->getAdminsOrAmbassadors($post->regionId);
        foreach (array_merge($orga, $ambassadors) as $o) {
            $foodsaver[$o['id']] = $o;
        }

        $bellData = Bell::create('blog_new_check_title', 'blog_new_check', 'fas fa-bullhorn', [
            'href' => '/blog?sub=edit&id=' . $postId
        ], [
            'user' => $this->session->user('name'),
            'teaser' => $this->sanitizer->tt($post->teaser, 100),
            'title' => $post->title
        ], BellType::createIdentifier(BellType::NEW_BLOG_POST, $postId));
        $this->bellGateway->addBellForUsers(array_keys($foodsaver), $bellData);

        return $postId;
    }

    public function editBlogPost(int $authorId, BlogPostData $post): void
    {
        $post->picture = $this->uploadsTransactions->fixUUIDForWriting($post->picture);
        $this->blogGateway->update_blog_entry($authorId, $post);

        if (!empty($post->picture)) {
            // cut the `/api/uploads/` in front of the UUID
            $uuid = $this->uploadsTransactions->getUUID($post->picture);
            $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $post->id);
        }
    }

    /**
     * Permanently delets a blog post. This also deletes the post's picture, if it has any.
     */
    public function deleteBlogPost(BlogPost $post): void
    {
        $this->blogGateway->del_blog_entry($post->id);

        if (!empty($post->picture)) {
            $oldUUID = $this->uploadsTransactions->getUUID($post->picture);
            $this->uploadsTransactions->deleteUploadedFile($oldUUID);
        }
    }
}
