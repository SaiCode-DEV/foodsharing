<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
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
            $nextOccurrence = $now->copy();
            if ($now->dayOfWeek !== $regularPickupTime['dow']) {
                $nextOccurrence = $nextOccurrence->next($regularPickupTime['dow']);
            }
            $nextOccurrence->setTimeFromTimeString($regularPickupTime['time']);
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
