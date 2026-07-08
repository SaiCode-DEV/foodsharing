<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
use Foodsharing\Modules\Store\DTO\PickupOption;
use Foodsharing\Modules\Store\DTO\RegularPickup;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PickupTransactions
{
    public function __construct(
        private readonly StoreTransactions $storeTransactions,
        private readonly RegularPickupGateway $regularPickupGateway,
        private readonly PickupGateway $oneTimePickupGateway,
        private readonly StoreGateway $storeGateway,
        private readonly MessageTransactions $messageTransactions,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly Session $session,
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
            if ($nextOccurrence->isBefore($now)) {
                $nextOccurrence->addWeek();
            }
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
            $profile = new Profile($fetcher['id'], $fetcher['name'], $fetcher['photo'], null);
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

    public function doLeavePickup(int $storeId, DateTime $pickupDate, int $fsId, string $message = '', bool $sendKickMessage = true)
    {
        $message = trim($message);

        if ($pickupDate < Carbon::now()) {
            throw new BadRequestHttpException('Cannot modify pickup in the past.');
        }

        if (!$this->oneTimePickupGateway->removeFetcher($fsId, $storeId, $pickupDate)) {
            throw new BadRequestHttpException('Failed to remove user from pickup');
        }

        if ($this->session->id() === $fsId) {
            $this->storeGateway->addStoreLog( // the user removed their own pickup
                $storeId,
                $fsId,
                null,
                $pickupDate,
                StoreLogAction::SIGN_OUT_SLOT
            );
        } else {
            $this->storeGateway->addStoreLog( // the user got kicked/the pickup got denied
                $storeId,
                $this->session->id(),
                $fsId,
                $pickupDate,
                StoreLogAction::REMOVED_FROM_SLOT,
                null,
                empty($message) ? null : $message
            );

            // send direct message to the user
            if ($sendKickMessage) {
                $formattedMessage = $this->storeTransactions->createKickMessage($fsId, $storeId, $pickupDate, $message);
                $this->messageTransactions->sendMessageToUser($fsId, $this->session->id(), $formattedMessage);
            }
        }
    }

    public function enrichPickupSlots(array $pickups, int $storeId): array
    {
        $team = [];
        foreach ($this->storeGateway->getStoreTeam($storeId) as $user) {
            $team[$user['id']] = $this->normalizeStoreUser($user);
        }
        foreach ($pickups as &$pickup) {
            foreach ($pickup['occupiedSlots'] as &$slot) {
                if (isset($team[$slot['foodsaverId']])) {
                    $slot['profile'] = $team[$slot['foodsaverId']];
                } else {
                    $details = $this->foodsaverGateway->getFoodsaver($slot['foodsaverId']);
                    $slot['profile'] = $this->normalizeStoreUser($details);
                }
                unset($slot['foodsaverId']);
            }
        }
        unset($pickup);
        usort($pickups, fn ($a, $b) => $a['date']->lt($b['date']) ? -1 : 1);

        $pickups = array_map(function ($pickup) {
            // Check required for history (does not contain dates)
            if (!empty($pickup['date'])) {
                // List of last and future and only future have a date on highest level
                $pickup['date'] = $pickup['date']->toIso8601String();
            }

            foreach ($pickup['occupiedSlots'] as &$slot) {
                // Check required for list of last and future pickups
                if (!empty($slot['date'])) {
                    // Time convertation needed for history
                    $slot['date'] = Carbon::createFromTimestamp($slot['date_ts'], new DateTimeZone('Europe/Berlin'))
                        ->toIso8601String();
                }
            }

            return $pickup;
        }, $pickups);

        return $pickups;
    }

    /**
     * Returns the response data for a foodsaver in store context: the above, plus
     * phone numbers, verification state, passed quiz level and if they're manager.
     *
     * @param array $data the user data from the database
     */
    private function normalizeStoreUser(array $data): array
    {
        if (!isset($data['id'])) {
            // the user can no longer be found
            $data['id'] = -1;
            $data['name'] = '?';
        }

        return [
            /* user-related data: */
            'id' => (int)$data['id'],
            'name' => $data['name'],
            'avatar' => $data['photo'] ?? null,
            'isSleeping' => $data['is_sleeping'] ?? false,
            'mobile' => $data['handy'] ?? '',
            'landline' => $data['telefon'] ?? '',
            // 'isVerified' => boolval($data['verified']),
            // 'roleLevel' => $data['quiz_rolle'], // should be added to FS:getFoodsaverDetails
            /* team-related data: */
            'isManager' => boolval($data['verantwortlich'] ?? false),
            // 'team_active' (membership status) should be included as well
        ];
    }

    public function createPickupOption(array $pickupData): PickupOption
    {
        $pickup = new PickupOption();
        $pickup->date = Carbon::createFromTimestamp($pickupData['timestamp'])->toDateTime();
        $pickup->store = new MinimalStoreIdentifier();
        $pickup->store->id = $pickupData['store_id'];
        $pickup->store->name = $pickupData['store_name'];
        $pickup->isConfirmed = boolval($pickupData['confirmed']);
        $pickup->slots = isset($pickupData['max_fetchers']) ? (int)$pickupData['max_fetchers'] : null;
        $pickup->occupiedSlots = array_map(
            fn ($id, $name, $avatar) => new Profile((int)$id, $name, $avatar == '' ? null : $avatar),
            str_getcsv((string)$pickupData['fs_ids']),
            str_getcsv((string)$pickupData['fs_names'], ',', '\''),
            str_getcsv((string)$pickupData['fs_avatars'])
        );
        $pickup->description = $pickupData['description'];

        return $pickup;
    }
}
