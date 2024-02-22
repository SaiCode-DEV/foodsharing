<?php

namespace Foodsharing\Modules\WorkGroup;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Modules\Region\ForumFollowerGateway;

class WorkGroupTransactions
{
    public function __construct(
        private readonly Mem $mem,
        private readonly WorkGroupGateway $workGroupGateway,
        private readonly ForumFollowerGateway $forumFollowerGateway
    ) {
    }

    /**
     * Removes a user from a working group and cancels the forum subscriptions.
     *
     * @throws \Exception
     */
    public function removeMemberFromGroup(int $groupId, int $memberId): void
    {
        $this->forumFollowerGateway->deleteForumSubscription($groupId, $memberId);
        $this->workGroupGateway->removeFromGroup($groupId, $memberId);
    }

    /**
     * Checks if an user is administrator for a working group.
     *
     * INFO: The reference information is cached on redis and the cache is updated by @see MaintenanceControl.
     *
     * @param int $userId UserId to check
     *
     * @return bool True is administrator, False no information present
     */
    public function isAdminForAWorkGroup(int $userId): bool
    {
        if ($allGroupAdmins = $this->mem->get('all_global_group_admins')) {
            return in_array($userId, unserialize($allGroupAdmins));
        }

        return false;
    }
}
