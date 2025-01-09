<?php

namespace Foodsharing\Modules\Region;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Mailbox\MailboxGateway;
use Foodsharing\Modules\Region\DTO\HierachicalRegion;
use Foodsharing\Modules\Region\DTO\RegionPickupStatistics;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\RestApi\Models\Notifications\Region;
use Foodsharing\RestApi\Models\Region\RegionForAdministration;
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
        $this->regionGateway->setRegionAdmins($region->id, $region->adminIds);
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
}
