<?php

namespace Foodsharing\Modules\Store;

use DateTime;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PickupInformation;
use Foodsharing\Modules\Store\DTO\RegularPickup;

class PickupTransactions
{
    public function __construct(
        private readonly StoreTransactions $storeTransactions,
        private readonly RegularPickupGateway $regularPickupGateway,
        private readonly PickupGateway $oneTimePickupGateway
    ) {
    }

    /**
     * Return all regular pickup for an store.
     *
     * @return RegularPickup[] List of regular pickups
     */
    public function getRegularPickup(int $storeId): array
    {
        return $this->regularPickupGateway->getRegularPickup($storeId);
    }

    /**
     * Replace the regular pick up of an store.
     *
     * @param int $storeId Store for replacement
     * @param RegularPickup[] $regularPickups List of new regular pickups
     *
     * @return RegularPickup[] List of the new regular pickups
     */
    public function replaceRegularPickup(int $storeId, array $regularPickups)
    {
        if (!$this->storeTransactions->existStore($storeId)) {
            throw new PickupValidationException(PickupValidationException::INVALID_STORE);
        }

        $timestamps = [];
        foreach ($regularPickups as $key => $pickup) {
            if (($pickup->maxCountOfSlots < 0) ||
                (StoreTransactions::MAX_SLOTS_PER_PICKUP < $pickup->maxCountOfSlots)) {
                throw new PickupValidationException(PickupValidationException::MAX_SLOT_COUNT_OUT_OF_RANGE, $key);
            }
            $timestamps[] = $pickup->weekday . $pickup->startTimeOfPickup;
        }
        $uniqueTimestamps = array_unique($timestamps);
        if (count($regularPickups) != count($uniqueTimestamps)) {
            throw new PickupValidationException(PickupValidationException::DUPLICATE_PICKUP_DAY_TIME);
        }

        $this->regularPickupGateway->deleteAllRegularPickups($storeId);
        foreach ($regularPickups as $regularPickup) {
            $this->regularPickupGateway->insertOrUpdateRegularPickup($storeId, $regularPickup);
        }
        $this->storeTransactions->triggerBellForRegularPickupChanged($storeId);

        return $this->getRegularPickup($storeId);
    }

    /**
     * Returns a list of possible pickups for a store depending on the date range which is provided.
     *
     * This function merges regular and one time pickups so that all real possible pickups are shown.
     *
     * @param int $storeId Identifier of the store to check
     * @param DateTime $from Start datetime for search
     * @param DateTime $lastDay End date time
     *
     * @return OneTimePickup[] List of pickups
     */
    public function getAllPickupsInRange(int $storeId, DateTime $from, DateTime $lastDay): array
    {
        $existingOneTimePickups = $this->oneTimePickupGateway->getOnetimePickupsForRange($storeId, $from, $lastDay);
        usort($existingOneTimePickups, fn ($a, $b) => strcmp($a->date->format('c'), $b->date->format('c')));
        $existingTimeStamps = array_map(fn (OneTimePickup $item) => $item->date->getTimestamp(), $existingOneTimePickups);

        // load all existing pickups
        $allAsOneTimePickups = $existingOneTimePickups;

        // add missing one time pickups from regular pickups
        $regularPickups = $this->regularPickupGateway->getRegularPickupsForRange($storeId, $from, $lastDay);
        foreach ($regularPickups as $regularPickup) {
            $generatedOneTimePickups = $regularPickup->convertToOneTimePickups($from, $lastDay);
            foreach ($generatedOneTimePickups as $generatedOneTimePickup) {
                $exists = in_array($generatedOneTimePickup->date->getTimestamp(), $existingTimeStamps);
                if (!$exists) {
                    $allAsOneTimePickups[] = $generatedOneTimePickup;
                }
            }
        }

        return $allAsOneTimePickups;
    }

    /**
     * Returns a list of pickups which are possible and who is part of the pickup.
     *
     * @param int $storeId Identifier of the store to check
     * @param DateTime $from Start datetime for search
     * @param DateTime $lastDay End date time
     *
     * @return PickupInformation[] List of pickups
     */
    public function getPickupsWithUsersForPickupsInRange(int $storeId, DateTime $from, DateTime $lastDay): array
    {
        $plannedPickups = $this->getAllPickupsInRange($storeId, $from, $lastDay);

        // find pickup fetchers for dates
        $signUps = $this->oneTimePickupGateway->getPickupSignUpsForDateRange($storeId, $from, $lastDay);

        // merge pickups with dates
        $listOfPickupInfos = array_map(fn (OneTimePickup $item) => new PickupInformation($item), $plannedPickups);

        $datesOfPlannedPickups = array_map(fn (OneTimePickup $item) => $item->date->getTimestamp(), $plannedPickups);
        foreach ($signUps as &$signUp) {
            $pickupIndex = array_search($signUp->date->getTimestamp(), $datesOfPlannedPickups);
            if ($pickupIndex !== false) {
                $listOfPickupInfos[$pickupIndex]->signUps[] = $signUp;
            }
        }

        return $listOfPickupInfos;
    }
}
