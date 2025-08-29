<?php

namespace Foodsharing\Modules\Region;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\FoodSharePoint\FoodSharePointGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\DTO\HierachicalRegion;
use Foodsharing\Modules\Region\DTO\PublicRegionData;
use Foodsharing\Modules\Region\DTO\RegionPickupStatistics;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\FoodSharePointPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\ReportPermissions;
use Foodsharing\Permissions\ResourcePermissions;
use Foodsharing\Permissions\VotingPermissions;
use Foodsharing\Permissions\WorkGroupPermissions;
use Foodsharing\RestApi\Models\Notifications\Region;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RegionTransactions
{
    final public const string NEW_FOODSAVER_VERIFIED = 'new_foodsaver_verified';
    final public const string NEW_FOODSAVER_NEEDS_VERIFICATION = 'new_foodsaver_needs_verification';
    final public const string NEW_FOODSAVER_NEEDS_INTRODUCTION = 'new_foodsaver_needs_introduction';

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly UnitGateway $unitGateway,
        private readonly RegionGateway $regionGateway,
        private readonly GroupFunctionGateway $groupFunctionGateway,
        private readonly MailboxGateway $mailboxGateway,
        private readonly RegionPermissions $regionPermissions,
        private readonly AchievementGateway $achievementGateway,
        private readonly CurrentUserUnitsInterface $currentUserUnits,
        private readonly ReportPermissions $reportPermissions,
        private readonly WorkGroupPermissions $workGroupPermissions,
        private readonly FoodSharePointGateway $foodSharePointGateway,
        private readonly EventGateway $eventGateway,
        private readonly FoodSharePointPermissions $foodSharePointPermissions,
        private readonly VotingPermissions $votingPermissions,
        private readonly AchievementPermissions $achievementPermissions,
        private readonly ForumFollowerGateway $forumFollowerGateway,
        private readonly ResourcePermissions $resourcePermissions,
    ) {
    }

    public function getJoinMessage(int $id, bool $verified): string
    {
        if ($verified) {
            return self::NEW_FOODSAVER_VERIFIED;
        }

        $verifiedBefore = $this->foodsaverGateway->foodsaverWasVerifiedBefore($id);

        return $verifiedBefore ? self::NEW_FOODSAVER_NEEDS_VERIFICATION : self::NEW_FOODSAVER_NEEDS_INTRODUCTION;
    }

    /**
     * Returns a list of region which the user is directly related (not the indirect parents).
     *
     * @param int $fsId foodsaver identifier of user
     *
     * @return UserUnit[] List of regions where the use is part
     */
    public function getUserRegions(int $fsId): array
    {
        return $this->unitGateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($fsId, UnitType::getRegionTypes());
    }

    /**
     * Returns details of a region. Makes sure that the moderated flag is properly set for regions of certain types.
     */
    public function getRegionDetails(int $regionId): array
    {
        $region = $this->regionGateway->getRegionDetails($regionId);
        if ($region) {
            $big = [UnitType::BIG_CITY, UnitType::FEDERAL_STATE, UnitType::COUNTRY];
            $region['moderated'] = $region['moderated'] || in_array($region['type'], $big);
        }

        return $region;
    }

    /**
     * Updates the user's notification setting for each region individually.
     *
     * @param Region[] $regions
     */
    public function updateRegionNotification(int $userId, array $regions): void
    {
        foreach ($regions as $region) {
            $this->regionGateway->updateRegionNotification($userId, $region->id, $region->notifyByEmailAboutNewThreads);
        }
    }

    public function buildRegionHierarchie(array $regions, int $rootId): ?HierachicalRegion
    {
        $regionsByParentId = [];
        foreach ($regions as &$region) {
            $regionsByParentId[$region->parentId][] = &$region;
            if ($region->id === $rootId) {
                $root = &$region;
            }
        }
        if (!isset($root)) {
            return null;
        }

        return $this->buildHierarchy($root, $regionsByParentId);
    }

    private function buildHierarchy(HierachicalRegion $node, array $regionsByParentId): HierachicalRegion
    {
        $node->children = array_map(
            fn (HierachicalRegion $node) => $this->buildHierarchy($node, $regionsByParentId),
            $regionsByParentId[$node->id] ?? []
        );

        return $node;
    }

    public function getRegionForEditing(int $regionId): RegionForAdministration
    {
        $data = $this->regionGateway->getRegionForEditing($regionId);
        $region = RegionForAdministration::createFromArray($data);
        if ($region->type === UnitType::WORKING_GROUP) {
            $region->workgroupFunction = $this->groupFunctionGateway->getRegionGroupFunctionId($regionId, $region->parentId) ?? 0;
        }
        if ($mailboxId = $data['mailbox_id']) {
            $region->mailbox = $this->mailboxGateway->getMailboxname($mailboxId);
        }
        $region->allowHidingInForum = boolval($this->regionGateway->getRegionOption($region->id, RegionOptionType::ALLOW_HIDING_IN_FORUM));

        return $region;
    }

    public function editRegion(RegionForAdministration $region): void
    {
        $this->assertNoDuplicateFunctionGroup($region);
        $this->cleanUpRegionParameters($region);
        if ($this->regionGateway->regionHasAncestor($region->parentId, $region->id)) {
            throw new BadRequestHttpException('Cyclic region graph not allowed.');
        }
        try {
            $this->mailboxGateway->setRegionMailbox($region);
        } catch (UniqueConstraintViolationException) {
            throw new BadRequestHttpException('This mailbox name is already used.');
        }
        $this->updateRegionAdmins($region->id, $region->adminIds);
        $this->regionGateway->editRegion($region);

        $this->groupFunctionGateway->deleteRegionFunction($region->id);
        if ($region->workgroupFunction) {
            $this->groupFunctionGateway->addRegionFunction($region->id, $region->parentId, $region->workgroupFunction);
        }
        $this->regionGateway->setRegionOption($region->id, RegionOptionType::ALLOW_HIDING_IN_FORUM, strval(intval($region->allowHidingInForum)));
    }

    public function addRegion(RegionForAdministration $region): int
    {
        $this->assertNoDuplicateFunctionGroup($region);
        $this->cleanUpRegionParameters($region);
        if ($this->mailboxGateway->isMailboxNameUsed($region->mailbox)) {
            throw new BadRequestHttpException('This mailbox name is already used.');
        }

        $regionId = $this->regionGateway->addRegion($region);
        $this->mailboxGateway->setRegionMailbox($region);
        $this->regionGateway->setRegionAdmins($region->id, $region->adminIds);
        if ($region->allowHidingInForum) {
            $this->regionGateway->setRegionOption($region->id, RegionOptionType::ALLOW_HIDING_IN_FORUM, '1');
        }
        if (WorkgroupFunction::isValidFunction($region->workgroupFunction)) {
            $this->groupFunctionGateway->addRegionFunction($region->id, $region->parentId, $region->workgroupFunction);
        }

        return $regionId;
    }

    /**
     * Returns the pickup statistics of a region for all possible date formats.
     *
     * @param int $regionId the region for which to list the statistics
     */
    public function getRegionPickupStatistics(int $regionId): RegionPickupStatistics
    {
        $statistics = new RegionPickupStatistics();
        $statistics->daily = $this->regionGateway->listRegionPickupsByDate($regionId, '%Y-%m-%d');
        $statistics->weekly = $this->regionGateway->listRegionPickupsByDate($regionId, '%Y/%v');
        $statistics->monthly = $this->regionGateway->listRegionPickupsByDate($regionId, '%Y-%m');
        $statistics->yearly = $this->regionGateway->listRegionPickupsByDate($regionId, '%Y');

        return $statistics;
    }

    private function assertNoDuplicateFunctionGroup(RegionForAdministration $region): void
    {
        if (!$region->workgroupFunction) {
            return;
        }
        $currentGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId(
            $region->parentId,
            $region->workgroupFunction,
        );
        if ($currentGroupId && $currentGroupId !== $region->id) {
            throw new BadRequestHttpException('There cannot be more than one special working group per type in each region.');
        }
    }

    private function cleanUpRegionParameters(RegionForAdministration $region): void
    {
        $region->name = strip_tags($region->name);
        $region->emailName = strip_tags($region->emailName);
        if (!$region->mailbox) {
            $region->emailName = '';
        } elseif ($region->emailName) {
            $region->emailName = strip_tags($region->emailName);
        } else {
            $region->emailName = 'foodsharing ' . strip_tags($region->name);
        }
    }

    public function getPublicRegionData(int $regionId): ?PublicRegionData
    {
        $data = $this->regionGateway->getPublicRegionBasics($regionId);
        if (empty($data)) {
            return null;
        }
        $data->ancestors = $this->regionGateway->getRegionAncestors($regionId);
        $data->children = $this->regionGateway->getRegionChildren($regionId);
        $data->foodSharePoints = $this->foodSharePointGateway->getFoodSharePointsForRegion($regionId);
        $data->events = array_reverse($this->eventGateway->listForRegion($regionId, true));
        try {
            $data->statistics = $this->regionGateway->getBasicRegionStatistics($regionId);
        } catch (InvalidArgumentException $e) {
            /* Precomputed statistics do not exist, which means that either the region does not exist, it is a working
               group, or it is new and stats have not yet been calculated. */
            $data->statistics = null;
        }

        return $data;
    }

    public function getMenu(int $regionId, ?array $region = null): ?array
    {
        if (empty($region)) {
            $region = $this->regionGateway->getRegionDetails($regionId);
        }
        if (empty($region)) {
            return null;
        }

        $menu = [];
        $menu['id'] = $region['id'];
        $menu['name'] = $region['name'];
        $menu['type'] = $region['type'];
        $menu['parent_id'] = $region['parent_id'];
        $menu['mayHandleFoodsaverRegionMenu'] = $this->regionPermissions->mayHandleFoodsaverRegionMenu($regionId);
        $menu['hasConference'] = $this->regionPermissions->hasConference($region['type']);
        $menu['hasAchievements'] = $this->achievementGateway->regionHasAchievements($region['id']);
        $menu['mayAdministrateAchievements'] = $this->achievementPermissions->mayAdministrateAchievementsFromRegion($region['id']);
        $menu['mayCreatePoll'] = $this->votingPermissions->mayCreatePoll($region['id'], $region['type']);
        $menu['hasResources'] = $this->resourcePermissions->maySeeResources($region['id'], $region['type']);

        if ($this->currentUserUnits->isAdminFor($regionId)) {
            $menu['mailboxId'] = $region['mailbox_id'];
        }

        if (UnitType::isRegion($region['type'])) {
            $menu['isAdmin'] = $this->currentUserUnits->isAdminFor($regionId);
            $menu['mayAccessReports'] = $this->reportPermissions->mayAccessReportsForRegion($regionId);
            $menu['isReportAdmin'] = $this->reportPermissions->isReportAdmin($regionId);
            $menu['isArbitrationAdmin'] = $this->reportPermissions->isArbitrationAdmin($regionId);
            $menu['maySetRegionPin'] = $this->regionPermissions->maySetRegionPin($regionId);
            $menu['mayAddFoodSharePoints'] = $this->foodSharePointPermissions->mayAdd($region['id']);
        } else {
            $menu['isAdmin'] = $this->workGroupPermissions->mayEdit($region);
            $menu['hasSubgroups'] = $this->regionGateway->hasSubgroups($regionId);
            if (RegionIDs::isChainsGroup($regionId)) {
                $menu['isChainGroup'] = true;
            }
        }

        return $menu;
    }

    /**
     * Returns all ancestors of a region, including information about whether the given user is member of that region.
     * The last element of the list is the first region that isn't a group or that the user is a member in.
     */
    public function getInaccessibleRegionRedirects(int $deniedRegionId, int $foodsaverId): array
    {
        $ancestors = $this->regionGateway->getRegionAncestorMemberships($deniedRegionId, $foodsaverId);
        for ($i = 0; $i < count($ancestors); ++$i) {
            if ($ancestors[$i]['type'] !== UnitType::WORKING_GROUP || $ancestors[$i]['is_member']) {
                return array_slice($ancestors, 0, $i + 1);
            }
        }

        return [];
    }

    private function updateRegionAdmins(int $regionId, array $adminIds): void
    {
        $oldAdminIds = $this->foodsaverGateway->getRegionAmbassadorIds($regionId);
        $removedAdminIds = array_diff($oldAdminIds, $adminIds);
        foreach ($removedAdminIds as $removedAdminId) {
            $this->forumFollowerGateway->deleteForumSubscription($regionId, $removedAdminId, 1);
        }
        $this->regionGateway->setRegionAdmins($regionId, $adminIds);
    }

    public function getResponsibleUsersForFunction(int $regionId, int $workgroupFunction): array
    {
        $functionGroupId = $this->groupFunctionGateway->getRegionFunctionGroupId($regionId, $workgroupFunction);
        if ($functionGroupId) {
            $responsibleUsers = $this->foodsaverGateway->getAdminsOrAmbassadors($functionGroupId);
        } else {
            $responsibleUsers = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        }

        return $responsibleUsers;
    }
}
