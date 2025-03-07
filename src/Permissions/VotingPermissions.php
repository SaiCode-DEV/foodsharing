<?php

namespace Foodsharing\Permissions;

use Carbon\CarbonInterval;
use DateTime;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Voting\DTO\Poll;
use Foodsharing\Modules\Voting\VotingGateway;

class VotingPermissions
{
    private Session $session;
    private VotingGateway $votingGateway;
    private RegionGateway $regionGateway;
    private GroupFunctionGateway $groupFunctionGateway;
    public CarbonInterval $MIN_POLL_EDIT_TIME;

    public function __construct(
        Session $session,
        VotingGateway $votingGateway,
        RegionGateway $regionGateway,
        GroupFunctionGateway $groupFunctionGateway,
        private CurrentUserUnitsInterface $currentUserUnits,
    ) {
        $this->session = $session;
        $this->votingGateway = $votingGateway;
        $this->regionGateway = $regionGateway;
        $this->groupFunctionGateway = $groupFunctionGateway;
        $this->MIN_POLL_EDIT_TIME = CarbonInterval::hours(1);
    }

    public function maySeePoll(Poll $poll): bool
    {
        return $this->currentUserUnits->mayBezirk($poll->regionId);
    }

    public function mayListPolls(int $regionId): bool
    {
        return $this->currentUserUnits->mayBezirk($regionId);
    }

    public function maySeeResults(Poll $poll): bool
    {
        return $poll->endDate < new DateTime();
    }

    public function mayVote(Poll $poll): bool
    {
        // only as member of the region
        if (!$this->currentUserUnits->mayBezirk($poll->regionId)) {
            return false;
        }

        // only in ongoing polls
        $now = new DateTime();
        if ($poll->startDate > $now || $poll->endDate < $now) {
            return false;
        }

        // only if not voted yet
        try {
            return $this->votingGateway->getVoteDatetime($poll->id, $this->session->id()) === null;
        } catch (Exception) {
            return false;
        }
    }

    public function mayCreatePoll(int $regionId, ?int $regionType = null): bool
    {
        if (!$this->currentUserUnits->mayBezirk($regionId) || !$this->session->isVerified()) {
            return false;
        }

        if (is_null($regionType)) {
            $regionType = $this->regionGateway->getType($regionId);
        }
        if (UnitType::isGroup($regionType)) {
            return $this->currentUserUnits->isAdminFor($regionId);
        } else {
            $votingGroup = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, WorkgroupFunction::VOTING);

            return !empty($votingGroup) && $this->currentUserUnits->isAdminFor($votingGroup);
        }
    }

    public function mayEditPoll(Poll $poll): bool
    {
        if ($this->session->id() != $poll->authorId) {
            return false;
        }

        return new DateTime() < $poll->startDate;
    }
}
