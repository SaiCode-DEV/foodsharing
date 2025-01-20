<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\QuizID;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Quiz\QuizGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\WallPost\WallPostGateway;

class WallPostPermissions
{
    public function __construct(
        private readonly RegionGateway $regionGateway,
        private readonly EventGateway $eventGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly FoodSharePointPermissions $fspPermission,
        private readonly QuizPermissions $quizPermissions,
        private readonly StorePermissions $storePermissions,
        private readonly FoodSharePointGateway $fspGateway,
        private readonly WallPostGateway $wallPostGateway,
        private readonly QuizGateway $quizGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly Session $session,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
    ) {
    }

    public function mayReadWall(WallType $target, int $targetId): bool
    {
        if ($target == WallType::FOODSAVER_REPORTS && $this->session->id() === $targetId) {
            return false;
        }
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        switch ($target) {
            case WallType::PROFILE:
                return $this->session->id() > 0;
            case WallType::WORKING_GROUP:
                return $this->regionGateway->hasMember($this->session->id() ?? 0, $targetId);
            case WallType::REGION:
                return true;
            case WallType::EVENT:
                $event = $this->eventGateway->getEvent($targetId);

                return !is_null($event) && $this->eventPermissions->mayCommentInEvent($event);
            case WallType::FOOD_SHARE_POINT:
                return true;
            case WallType::QUIZ_QUESTION:
                $quizId = $this->quizGateway->getQuizIdFromQuestionId($targetId);

                return $this->quizPermissions->mayReadQuiz(QuizID::tryFrom($quizId));
            case WallType::PROFILE_NOTES:
                return $this->session->mayRole(Role::ORGA);
            case WallType::STORE:
                return $this->storePermissions->mayReadStoreWall($targetId);
            default:
                return false;
        }
    }

    public function mayWriteWall(WallType $target, int $targetId): bool
    {
        if (!$this->session->id()) {
            return false;
        }

        return match ($target) {
            WallType::PROFILE => $this->session->id() === $targetId,
            WallType::QUIZ_QUESTION => true,
            WallType::REGION => $this->regionPermissions->hasFunctionGroupPermissionForRegion(WorkgroupFunction::PR, $targetId),
            default => $this->mayReadWall($target, $targetId),
        };
    }

    /**
     * Whether the user may delete any post from the given wall. For specific posts see mayDeleteWallPost.
     */
    public function mayDeleteWall(WallType $target, int $targetId): bool
    {
        if (!$this->session->id()) {
            return false;
        } elseif ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        switch ($target) {
            case WallType::WORKING_GROUP:
                return $this->currentUserUnits->isAdminFor($targetId);
            case WallType::REGION:
                return $this->mayWriteWall($target, $targetId);
            case WallType::QUIZ_QUESTION:
                $quizId = $this->quizGateway->getQuizIdFromQuestionId($targetId);

                return $this->quizPermissions->mayEditQuiz(QuizID::tryFrom($quizId));
            case WallType::PROFILE_NOTES:
            case WallType::FOODSAVER_REPORTS:
                return $this->mayReadWall($target, $targetId);
            case WallType::FOOD_SHARE_POINT:
                $fsp = $this->fspGateway->getFoodSharePoint($targetId);
                if (empty($fsp) || empty($fsp['bezirk_id'])) {
                    return false;
                }

                return $this->fspPermission->mayAdministrateFoodSharePoint($targetId)
                    || $this->fspPermission->mayDeleteFoodSharePointWallPostOfRegion($fsp['bezirk_id']);
            case WallType::STORE:
                return $this->storePermissions->mayEditStore($targetId);
            default:
                return false;
        }
    }

    public function mayDeleteWallPost(WallType $target, int $targetId, int $postId): bool
    {
        return $this->mayDeleteWall($target, $targetId) || $this->wallPostGateway->getAuthorId($postId) === $this->session->id();
    }
}
