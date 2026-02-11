<?php

namespace Foodsharing\Modules\WallPost;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointTransactions;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Permissions\QuizPermissions;
use Foodsharing\Permissions\UploadsPermissions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WallPostTransactions
{
    public function __construct(
        private readonly WallPostGateway $wallPostGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly UploadsPermissions $uploadsPermissions,
        private readonly QuizGateway $quizGateway,
        private readonly QuizPermissions $quizPermissions,
        private readonly EventGateway $eventGateway,
        private readonly RegionGateway $regionGateway,
        private readonly BellTransactions $bellTransactions,
        private readonly StoreGateway $storeGateway,
        private readonly FoodSharePointTransactions $foodSharePointTransactions,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly Session $session,
    ) {
    }

    /**
     * Adds a post to a wall and takes care of marking the attached pictures, if there are any.
     *
     * @param WallPost $wallPost the post to be added
     * @param WallType $target the wall type
     * @param int $targetId id of the wall
     * @return WallPost the post as it was stored in the database
     */
    public function addPost(WallPost $wallPost, WallType $target, int $targetId): WallPost
    {
        // Check if the user is allowed to use the uploaded files
        if (!empty($wallPost->pictures)) {
            foreach ($wallPost->pictures as $picture) {
                $uuid = substr((string)$picture, 13);
                if (!$this->uploadsPermissions->maySetUploadUsage($uuid)) {
                    throw new AccessDeniedHttpException('Invalid upload UUID');
                }
            }
        }

        $postId = $this->wallPostGateway->addPost($wallPost, $this->session->id(), $target, $targetId);
        $post = $this->wallPostGateway->getPost($postId);

        if (!empty($post->pictures)) {
            foreach ($post->pictures as $picture) {
                $uuid = substr((string)$picture, 13);
                $this->uploadsGateway->setUsage([$uuid], UploadUsage::WALL_POST, $postId);
            }
        }

        $bellData = $this->getWallPostBellData($wallPost, $target, $targetId);
        if ($bellData) {
            $this->bellTransactions->addGroupedBellEvent($bellData['recipients'], $bellData['bell'], $postId);
        }

        $this->additionalWallPostSideEffects($wallPost, $target, $targetId);

        return $post;
    }

    public function deletePost(int $postId, WallType $target, int $targetId): void
    {
        $post = $this->wallPostGateway->getPost($postId);
        if (!empty($post->pictures)) {
            foreach ($post->pictures as $picture) {
                if (str_starts_with($picture, '/api/uploads/')) {
                    $oldUUID = substr($picture, 13);
                    $this->uploadsTransactions->deleteUploadedFile($oldUUID);
                } elseif (!empty($picture)) {
                    /* Delete all resized files of the old picture. The DTO contains the file as '{id}.jpg'. The real
                    path is './images/wallpost/{format}_{id}.jpg'. Use a placeholder because there might be several
                    resized formats of the original file. */
                    foreach (glob('./images/wallpost/*' . $picture) as $file) {
                        unlink($file);
                    }
                }
            }
        }

        $bellData = $this->getWallPostBellData(null, $target, $targetId);
        if ($bellData) {
            $this->bellTransactions->removeGroupedBellEvent($bellData['recipients'], $bellData['bell'], $postId);
        }

        switch ($target) {
            case WallType::STORE:
                $this->storeGateway->addStoreLog($targetId, $this->session->id(), $post->author->id, $post->time, StoreLogAction::DELETED_FROM_WALL, $post->body);
                break;
        }

        $this->wallPostGateway->deletePost($postId, $target);
    }

    private function getWallPostBellData(?WallPost $wallPost, WallType $target, int $targetId): ?array
    {
        switch ($target) {
            case WallType::QUIZ_QUESTION:
                $quizId = $this->quizGateway->getQuizIdFromQuestionId($targetId);
                $recipients = array_column($this->quizPermissions->getQuizAdmins(QuizID::from($quizId)), 'id');
                $bell = Bell::create(
                    'new_quiz_comment_title',
                    'new_quiz_comment',
                    'fas fa-comment',
                    ['href' => "/quiz/edit/$quizId?question=$targetId"],
                    [
                        'comment' => $wallPost ? $wallPost->body : '',
                        'questionId' => $targetId,
                    ],
                    BellType::createIdentifier(BellType::NEW_QUESTION_COMMENT, $targetId)
                );
                break;
            case WallType::EVENT:
                $event = $this->eventGateway->getEvent($targetId);
                $region = $this->regionGateway->getRegionName($event->regionId);
                $attendees = $this->eventGateway->getEventAttendees($targetId);
                $recipients = array_merge($attendees['accepted'], $attendees['maybe']);
                $recipients = array_column($recipients, 'id');
                $bell = Bell::create(
                    'event_post_title',
                    'event_post',
                    'fas fa-calendar',
                    ['href' => '/event/' . $targetId],
                    [
                        'event' => $event->name,
                        'region' => $region,
                    ],
                    BellType::createIdentifier(BellType::NEW_EVENT_POST, $targetId)
                );
                break;
            case WallType::STORE:
                $recipients = array_column($this->storeGateway->getStoreTeam($targetId), 'id');
                $bell = Bell::create(
                    'store_wall_post_title',
                    'store_wall_post',
                    'fas fa-thumbtack',
                    ['href' => '/store/' . $targetId],
                    ['name' => $this->storeGateway->getStoreName($targetId)],
                    BellType::createIdentifier(BellType::STORE_WALL_POST, $targetId)
                );
                break;
            case WallType::FOOD_SHARE_POINT:
                $recipients = $this->foodSharePointGateway->getInfoFollowerIds($targetId);
                $bell = Bell::create(
                    'ft_update_title',
                    'ft_update',
                    'fas fa-recycle',
                    ['href' => '/fairteiler/' . $targetId],
                    [
                        'name' => $this->foodSharePointGateway->getFoodSharePoint($targetId)->name,
                        'teaser' => substr($wallPost->body ?? '', 0, 100)
                    ],
                    BellType::createIdentifier(BellType::FOOD_SHARE_POINT_POST, $targetId)
                );
                break;
            default:
                return null;
        }

        $recipients = array_diff($recipients, [$this->session->id()]);
        $bell->vars['user'] = $this->session->user('name');

        return ['recipients' => $recipients, 'bell' => $bell];
    }

    private function additionalWallPostSideEffects(?WallPost $wallPost, WallType $target, int $targetId): void
    {
        switch ($target) {
            case WallType::FOOD_SHARE_POINT:
                $this->foodSharePointTransactions->sendNewFoodSharePointMailNotifications($targetId);
                break;
            default:
                break;
        }
    }
}
