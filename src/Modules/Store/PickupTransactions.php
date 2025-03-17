<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PickupInformation;
use Foodsharing\Modules\Store\DTO\PickupOption;
use Foodsharing\Modules\Store\DTO\RegularPickup;

class PickupTransactions
{
    public function __construct(
        private readonly StoreTransactions $storeTransactions,
        private readonly RegularPickupGateway $regularPickupGateway,
        private readonly PickupGateway $oneTimePickupGateway,
        private readonly StoreGateway $storeGateway,
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

    /**
     * @return PickupOption[] All pickup options that the current user has
     */
    public function getPickupOptions(int $userId): array
    {
        $stores = $this->storeGateway->getStoresForUser($userId, ['id', 'name', 'prefetchtime']);
        $storesMap = [];
        foreach ($stores as $store) {
            $storesMap[$store['id']] = $store;
        }

        // functions to get a unique identifier for a pickup.
        // this is used prevent excessive searching for pickupOptions by hashing them instead.
        $getIdentifier = fn (DateTime $date, int $storeId) => $date->getTimestamp() . '-' . $storeId;
        $getIdentifierFromPickupOption = fn (PickupOption $pickupOption) => $getIdentifier($pickupOption->date, $pickupOption->store->id);

        // Generate regular pickups
        $regularPickupTimes = $this->regularPickupGateway->getRegularPickupTimesForStoresOfUser($userId);
        $now = Carbon::now();
        $pickupOptions = [];
        foreach ($regularPickupTimes as $regularPickupTime) {
            $store = $storesMap[$regularPickupTime['betrieb_id']];
            $end = $now->copy()->addSeconds($store['prefetchtime']);
            $nextOccurrence = $now->next($regularPickupTime['dow'])->setTimeFromTimeString($regularPickupTime['time']);
            while ($nextOccurrence->lessThanOrEqualTo($end)) {
                $pickupOption = new PickupOption();
                $pickupOption->date = $nextOccurrence->copy();
                $pickupOption->store = MinimalStoreIdentifier::createFromArray($store);
                $pickupOption->slots = $regularPickupTime['fetcher'];
                $pickupOption->description = $regularPickupTime['description'];
                $pickupOptions[$getIdentifierFromPickupOption($pickupOption)] = $pickupOption;

                $nextOccurrence->addWeek();
            }
        }

        // Add oneTimePickups
        $oneTimePickups = $this->oneTimePickupGateway->getFuturePickupTimesForStoresOfUser($userId);
        foreach ($oneTimePickups as $oneTimePickup) {
            $store = $storesMap[$oneTimePickup['betrieb_id']];
            $pickupOption = new PickupOption();
            $pickupOption->date = new Carbon($oneTimePickup['time']);
            $pickupOption->store = MinimalStoreIdentifier::createFromArray($store);
            $pickupOption->slots = $oneTimePickup['fetchercount'];
            $pickupOption->description = $oneTimePickup['description'];
            $pickupOptions[$getIdentifierFromPickupOption($pickupOption)] = $pickupOption;
        }

        // Add fetchers as occupied slots
        $fetchers = $this->oneTimePickupGateway->getFutureFetchersForStoresOfUser($userId);
        foreach ($fetchers as $fetcher) {
            $identifier = $getIdentifier(new Carbon($fetcher['date']), $fetcher['betrieb_id']);
            if (!isset($pickupOptions[$identifier])) {
                continue; // fetcher found for a pickup that isn't in the db. This should never happen.
            }
            $pickupOption = $pickupOptions[$identifier];
            $profile = new Profile($fetcher);
            $pickupOption->occupiedSlots[] = $profile;
            if ($profile->id === $userId) {
                $pickupOption->isConfirmed = boolval($fetcher['confirmed']);
            }
        }

        // Filtering (exclude completely filled slots without the user in them)
        $pickupOptions = array_values(array_filter(
            $pickupOptions,
            fn ($pickupOption) => count($pickupOption->occupiedSlots) < $pickupOption->slots || !is_null($pickupOption->isConfirmed)
        ));

        // Sort all options by time
        usort($pickupOptions, fn ($a, $b) => $a->date <=> $b->date);

        return $pickupOptions;
    }
}
