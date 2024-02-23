<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Modules\WorkGroup\WorkGroupTransactions;

class WallPostPermissions
{
    public function __construct(
        private readonly RegionGateway $regionGateway,
        private readonly EventGateway $eventGateway,
        private readonly EventPermissions $eventPermissions,
        private readonly FoodSharePointPermissions $fspPermission,
        private readonly FoodSharePointGateway $fspGateway,
        private readonly WallPostGateway $wallPostGateway,
        private readonly Session $session,
        private readonly WorkGroupTransactions $workGroupTransactions
    ) {
    }

    public function mayReadWall(string $target, int $targetId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        switch ($target) {
            case 'foodsaver':
                return $this->session->id() > 0;
            case 'bezirk':
                return $this->regionGateway->hasMember($this->session->id(), $targetId);
            case 'event':
                $event = $this->eventGateway->getEvent($targetId);

                return !is_null($event) && $this->eventPermissions->mayCommentInEvent($event);
            case 'fairteiler':
                return true;
            case 'question':
                return $this->regionGateway->hasMember($this->session->id(), RegionIDs::QUIZ_AND_REGISTRATION_WORK_GROUP);
            case 'usernotes':
            case 'report':
                return $this->regionGateway->hasMember($this->session->id(), RegionIDs::EUROPE_REPORT_TEAM);
            case 'application':
                return $this->workGroupTransactions->isAdminForAWorkGroup($this->session->id());
            default:
                return false;
        }
    }

    public function mayWriteWall(string $target, int $targetId): bool
    {
        if (!$this->session->id()) {
            return false;
        }

        return match ($target) {
            'foodsaver' => $this->session->id() === $targetId,
            'question' => true,
            default => $this->mayReadWall($target, $targetId),
        };
    }

    /**
     * Whether the user may delete any post from the given wall. For specific posts see mayDeleteWallPost.
     */
    public function mayDeleteWall(string $target, int $targetId): bool
    {
        if (!$this->session->id()) {
            return false;
        } elseif ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        switch ($target) {
            case 'bezirk':
                return $this->regionGateway->isAdmin($this->session->id(), $targetId);
            case 'question':
            case 'usernotes':
            case 'report':
                return $this->mayReadWall($target, $targetId);
            case 'fairteiler':
                $fsp = $this->fspGateway->getFoodSharePoint($targetId);
                if (empty($fsp) || empty($fsp['bezirk_id'])) {
                    return false;
                }

                return $this->fspPermission->mayAdministrateFoodSharePoint($targetId)
                    || $this->fspPermission->mayDeleteFoodSharePointWallPostOfRegion($fsp['bezirk_id']);
            default:
                return false;
        }
    }

    public function mayDeleteWallPost(string $target, int $targetId, int $postId): bool
    {
        return $this->mayDeleteWall($target, $targetId) || $this->wallPostGateway->getAuthorId($postId) === $this->session->id();
    }
}
