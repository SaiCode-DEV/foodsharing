<?php

namespace Foodsharing\Permissions;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\ThreadStatus;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\ForumGateway;

class ForumPermissions
{
    private readonly ForumGateway $forumGateway;
    private readonly Session $session;
    private readonly GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        ForumGateway $forumGateway,
        Session $session,
        GroupFunctionGateway $groupFunctionGateway
    ) {
        $this->forumGateway = $forumGateway;
        $this->session = $session;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    public function mayStartUnmoderatedThread(array $region, $ambassadorForum): bool
    {
        if (!$this->session->user('verified')) {
            return false;
        }
        $regionId = $region['id'];

        if ($ambassadorForum) {
            return $this->mayPostToRegion($regionId, $ambassadorForum);
        }

        $moderationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::MODERATION);

        if (empty($moderationGroup)) {
            if ($this->session->isAmbassadorForRegion($regionId)) {
                return true;
            }
        } elseif ($this->session->isAdminFor($moderationGroup)) {
            return true;
        }

        return !$region['moderated'];
    }

    public function mayPostToRegion(int $regionId, $ambassadorForum): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        if ($ambassadorForum && !$this->session->isAdminFor($regionId)) {
            return false;
        }
        if (!$this->session->mayBezirk($regionId)) {
            return false;
        }

        return true;
    }

    public function mayAccessForum($forumId, $forumSubId): bool
    {
        if ($forumSubId !== 0 && $forumSubId !== 1) {
            return false;
        }

        return $this->mayPostToRegion($forumId, $forumSubId);
    }

    public function mayPostToThread(int $threadId): bool
    {
        if (!$this->mayAccessThread($threadId)) {
            return false;
        }

        if ($this->forumGateway->getThreadStatus($threadId) === ThreadStatus::OPEN) {
            return true;
        }

        return $this->mayModerate($threadId);
    }

    public function mayModerate(int $threadId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        $forums = $this->forumGateway->getForumsForThread($threadId);

        foreach ($forums as $forum) {
            $moderationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($forum['forumId'], WorkgroupFunction::MODERATION);
            if (empty($moderationGroup)) {
                if ($this->session->isAdminFor($forum['forumId'])) {
                    return true;
                }
            } elseif ($this->session->isAdminFor($moderationGroup)) {
                return true;
            }
        }

        return false;
    }

    public function mayRename(int $threadId): bool
    {
        if ($this->mayModerate($threadId)) {
            return true;
        }

        return $this->forumGateway->getThread($threadId)['creator_id'] == $this->session->id();
    }

    public function mayAccessThread(int $threadId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $forums = $this->forumGateway->getForumsForThread($threadId);
        foreach ($forums as $forum) {
            if ($this->mayAccessForum($forum['forumId'], $forum['forumSubId'])) {
                return true;
            }
        }

        return false;
    }

    public function mayAccessAmbassadorBoard(int $regionId): bool
    {
        return $this->mayPostToRegion($regionId, true);
    }

    public function mayChangeStickiness(int $regionId): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }

        $moderationGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::MODERATION);

        if (empty($moderationGroup)) {
            if ($this->session->isAdminFor($regionId)) {
                return true;
            }
        } elseif ($this->session->isAdminFor($moderationGroup)) {
            return true;
        }

        return false;
    }

    public function mayDeletePost(array $post): bool
    {
        if ($this->session->mayRole(Role::ORGA)) {
            return true;
        }
        if ($post['author_id'] == $this->session->id()) {
            return true;
        }

        return false;
    }

    public function mayDeleteThread(array $thread): bool
    {
        return !$thread['active'] && $this->mayModerate($thread['id']);
    }

    public function maySearchEveryForum(): bool
    {
        return $this->session->mayRole(Role::ORGA);
    }
}
