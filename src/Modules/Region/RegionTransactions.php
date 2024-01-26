<?php

namespace Foodsharing\Modules\Region;

use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Unit\DTO\UserUnit;
use Foodsharing\Modules\Unit\UnitGateway;
use Foodsharing\RestApi\Models\Notifications\Region;

class RegionTransactions
{
    final public const NEW_FOODSAVER_VERIFIED = 'new_foodsaver_verified';
    final public const NEW_FOODSAVER_NEEDS_VERIFICATION = 'new_foodsaver_needs_verification';
    final public const NEW_FOODSAVER_NEEDS_INTRODUCTION = 'new_foodsaver_needs_introduction';

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly UnitGateway $unitGateway,
        private readonly RegionGateway $regionGateway,
    ) {
    }

    public function getJoinMessage(array $userData): string
    {
        if (!isset($userData['id'])) {
            throw new \InvalidArgumentException('Invalid user data. Id not set.');
        }

        if (isset($userData['verified']) && $userData['verified']) {
            return self::NEW_FOODSAVER_VERIFIED;
        }

        $verifiedBefore = $this->foodsaverGateway->foodsaverWasVerifiedBefore($userData['id']);

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
}
