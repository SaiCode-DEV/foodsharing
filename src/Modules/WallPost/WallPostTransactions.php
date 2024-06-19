<?php

namespace Foodsharing\Modules\WallPost;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Permissions\QuizPermissions;

class WallPostTransactions
{
    public function __construct(
        private readonly WallPostGateway $wallPostGateway,
        private readonly UploadsGateway $uploadsGateway,
        private readonly QuizGateway $quizGateway,
        private readonly QuizPermissions $quizPermissions,
        private readonly BellGateway $bellGateway,
        private readonly EventGateway $eventGateway,
        private readonly RegionGateway $regionGateway,
        private readonly BellTransactions $bellTransactions,
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

        switch ($target) {
            case 'question':
                $this->sendQuestionCommentBell($targetId, $post);
                break;
            case 'event':
                $this->sendEventCommentBell($targetId);
                break;
        }

        return $post;
    }

    private function sendQuestionCommentBell(int $questionId, WallPost $post)
    {
        $quizId = $this->quizGateway->getQuizIdFromQuestionId($questionId);
        $recipients = $this->quizPermissions->getQuizAdmins($quizId);
        $bell = Bell::create(
            'new_quiz_comment_title',
            'new_quiz_comment',
            'fas fa-comment',
            ['href' => "/quiz/edit/$quizId?question=$questionId"],
            [
                'comment' => $post->body,
                'questionId' => $questionId,
                'user' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::NEW_QUESTION_COMMENT, $questionId)
        );
        $this->bellGateway->addBell($recipients, $bell);
    }

    public function deletePost(int $postId, string $target, int $targetId): void
    {
        $this->wallPostGateway->deletePost($postId, $target);

        switch ($target) {
            case 'event':
                $this->removeEventCommentBell($targetId);
                break;
        }
    }

    private function sendEventCommentBell(int $eventId): void
    {
        $bellData = $this->getEventCommentBellData($eventId);
        $this->bellTransactions->addGroupedBellEvent(...$bellData);
    }

    private function removeEventCommentBell(int $eventId): void
    {
        $bellData = $this->getEventCommentBellData($eventId);
        $this->bellTransactions->removeGroupedBellEvent(...$bellData);
    }

    private function getEventCommentBellData(int $eventId): array
    {
        $event = $this->eventGateway->getEvent($eventId, true);
        $region = $this->regionGateway->getRegionName($event['bezirk_id']);
        $recipients = array_merge($event['invites']['accepted'], $event['invites']['maybe']);
        $recipientIds = array_column($recipients, 'id');
        $recipientIds = array_diff($recipientIds, [$this->session->id()]);

        $baseBell = Bell::create(
            'event_post_title',
            'event_post',
            'fas fa-calendar',
            ['href' => '/event/' . $eventId],
            [
                'event' => $event['name'],
                'region' => $region,
                'user' => $this->session->user('name'),
            ],
            BellType::createIdentifier(BellType::NEW_EVENT_POST, $eventId)
        );

        return [$recipientIds, $baseBell, $eventId];
    }
}
