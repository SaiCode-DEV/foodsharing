<?php

namespace Foodsharing\Modules\WallPost;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;

class WallPostTransactions
{
    public function __construct(
        private readonly WallPostGateway $wallPostGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly Session $session,
    ) {
    }

    /**
     * Adds a post to a wall and takes care of marking the attached pictures, if there are any.
     *
     * @param WallPost $wallPost the post to be added
     * @param string $target the wall type
     * @param int $targetId id of the wall
     * @return WallPost the post as it was stored in the database
     */
    public function addPost(WallPost $wallPost, string $target, int $targetId): WallPost
    {
        $postId = $this->wallPostGateway->addPost($wallPost, $this->session->id(), $target, $targetId);
        $post = $this->wallPostGateway->getPost($postId);

        if (!empty($post->pictures)) {
            foreach ($post->pictures as $picture) {
                $uuid = substr($picture, 13);
                $this->uploadsGateway->setUsage([$uuid], UploadUsage::WALL_POST, $postId);
            }
        }

        return $post;
    }
}
