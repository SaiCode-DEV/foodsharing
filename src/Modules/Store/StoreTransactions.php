<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use DateTime;
use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionOptionType;
use Foodsharing\Modules\Core\DBConstants\Store\ConvinceStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\Milestone;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DTO\MinimalIdentifier;
use Foodsharing\Modules\Core\DTO\PatchGeoLocation;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\CreateStoreData;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PatchAddress;
use Foodsharing\Modules\Store\DTO\PatchContactData;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\PatchStoreOptionModel;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreListInformation;
use Foodsharing\Modules\Store\DTO\StoreStatusForMember;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\WeightHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

class StoreTransactions
{
    public const DEFAULT_USER_SHOWN_STORE_COOPERATION_STATE = [
        CooperationStatus::UNCLEAR,
        CooperationStatus::NO_CONTACT,
        CooperationStatus::IN_NEGOTIATION,
        CooperationStatus::COOPERATION_STARTING,
        CooperationStatus::COOPERATION_ESTABLISHED
    ];

    public const MAX_SLOTS_PER_PICKUP = 10;
    // status constants for getAvailablePickupStatus
    private const STATUS_RED_TODAY_TOMORROW = 3;
    private const STATUS_ORANGE_3_DAYS = 2;
    private const STATUS_YELLOW_5_DAYS = 1;
    private const STATUS_GREEN = 0;
    private const MAX_PICKUP_DESCRIPTION_LENGTH = 100;

    public function __construct(
        private readonly MessageGateway $messageGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly StoreGateway $storeGateway,
        private readonly TranslatorInterface $translator,
        private readonly BellGateway $bellGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionGateway $regionGateway,
        private readonly Sanitizer $sanitizerService,
        private readonly Session $session
    ) {
    }

    /**
     * Returns a store's data including the team members in a format suitable for the frontend.
     *
     * @param int $userId the user who is requesting the data
     * @param int $storeId the store
     * @param bool $includeUserDetails whether to include phone numbers and last fetch dates for the team members
     */
    public function getMyStoreTeam(int $userId, int $storeId, bool $includeUserDetails): array
    {
        $store = $this->storeGateway->getMyStore($userId, $storeId);

        return $this->getDisplayedStoreTeam($store, $includeUserDetails);
    }

    /**
     * Get store applications for a specific user and store.
     *
     * @param int $userId   the ID of the user
     * @param int $storeId  the ID of the store
     *
     * @return array an array containing store requests
     */
    public function getStoreApplications(int $userId, int $storeId): array
    {
        $store = $this->storeGateway->getMyStore($userId, $storeId);

        return [
            'storeRequests' => $store['requests'] ?? [],
        ];
    }

    public function getCommonStoreMetadata($supressStoreChains = true): CommonStoreMetadata
    {
        $store = new CommonStoreMetadata();

        $store->groceries = array_map(function ($row) {
            return CommonLabel::createFromArray($row);
        }, $this->storeGateway->getBasics_groceries());

        $store->categories = [new CommonLabel(0, $this->translator->trans('store.nodeclaration')),
            ...array_map(function ($row) {
                return CommonLabel::createFromArray($row);
            }, $this->storeGateway->getStoreCategories())];

        $store->status = array_map(function ($row) {
            return CommonLabel::createFromArray($row);
        }, [
            ['id' => CooperationStatus::UNCLEAR->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => CooperationStatus::NO_CONTACT->value, 'name' => $this->translator->trans('storestatus.1')],
            ['id' => CooperationStatus::IN_NEGOTIATION->value, 'name' => $this->translator->trans('storestatus.2')],
            ['id' => CooperationStatus::COOPERATION_STARTING->value, 'name' => $this->translator->trans('storestatus.3a')],
            ['id' => CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US->value, 'name' => $this->translator->trans('storestatus.4')],
            ['id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'name' => $this->translator->trans('storestatus.5')],
            ['id' => CooperationStatus::GIVES_TO_OTHER_CHARITY->value, 'name' => $this->translator->trans('storestatus.6')],
            ['id' => CooperationStatus::PERMANENTLY_CLOSED->value, 'name' => $this->translator->trans('storestatus.7')],
        ]);

        $store->publicTimes = array_map(function ($row) {
            return CommonLabel::createFromArray($row);
        }, [
            ['id' => PublicTimes::NOT_SET->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => PublicTimes::IN_THE_MORNING->value, 'name' => $this->translator->trans('storeview.public_time_in_the_morning')],
            ['id' => PublicTimes::AT_NOON_IN_THE_AFTERNOON->value, 'name' => $this->translator->trans('storeview.public_time_at_noon_or_afternoon')],
            ['id' => PublicTimes::IN_THE_EVENING->value, 'name' => $this->translator->trans('storeview.public_time_in_the_evening')],
            ['id' => PublicTimes::AT_NIGHT->value, 'name' => $this->translator->trans('storeview.public_time_at_night')]
        ]);

        $store->convinceStatus = array_map(function ($row) {
            return CommonLabel::createFromArray($row);
        }, [
            ['id' => ConvinceStatus::NOT_SET->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => ConvinceStatus::NO_PROBLEM_AT_ALL->value, 'name' => $this->translator->trans('store.convince.none')],
            ['id' => ConvinceStatus::AFTER_SOME_PERSUASION->value, 'name' => $this->translator->trans('store.convince.some')],
            ['id' => ConvinceStatus::DIFFICULT_NEGOTIATION->value, 'name' => $this->translator->trans('store.convince.much')],
            ['id' => ConvinceStatus::LOOKED_BAD_BUT_WORKED->value, 'name' => $this->translator->trans('store.convince.final')]
        ]);

        if (!$supressStoreChains) {
            $store->storeChains = [new CommonLabel(0, $this->translator->trans('store.nodeclaration')),
                ...array_map(function ($row) {
                    return CommonLabel::createFromArray($row);
                }, $this->storeGateway->getBasics_chain())];
        }

        $store->weight = array_map(function ($row) {
            return CommonLabel::createFromArray($row);
        }, (new WeightHelper())->getWeightListEntries());

        return $store;
    }

    public function existStore($storeId)
    {
        return $this->storeGateway->storeExists($storeId);
    }

    /**
     * Return a list of store identifiers of reduced store information which belong to region.
     *
     * This list of stores contains all stores from sub regions.
     *
     * @param int $regionId Region identifier
     * @param bool $expand Expand information about store and region
     *
     * @return array<StoreListInformation> List of information
     *
     * @throws Exception
     */
    public function listOverviewInformationsOfStoresInRegion(int $regionId, bool $expand): array
    {
        $stores = $this->storeGateway->listStoresInRegion($regionId, true);

        return $this->arrayMapStoreListInformation($stores, $expand);
    }

    /**
     * Returns a list of stores where the user is a member of reduced store information.
     **
     * @param int $userId User identifier
     * @param bool $expand Expand information about store and region
     *
     * @return array<StoreListInformation> List of information
     *
     * @throws Exception
     */
    public function listOverviewInformationsOfStoresFromUser(int $userId, bool $expand): array
    {
        $stores = $this->storeGateway->listStoresInFromUser($userId);

        return $this->arrayMapStoreListInformation($stores, $expand);
    }

    private function arrayMapStoreListInformation(array $stores, bool $expand): array
    {
        return array_map(function (Store $store) use ($expand) {
            $requiredStoreInformation = StoreListInformation::loadFrom($store, !$expand);
            if ($expand) {
                $regionName = $this->regionGateway->getRegionName($store->region->id);
                $requiredStoreInformation->region->name = $regionName;
            }

            return $requiredStoreInformation;
        }, $stores);
    }

    /**
     * Provides information about a store and reduce the information to essential parts.
     *
     * @param int $storeId Identifier of store
     * @param bool $showDetails Leaves details about stores like description, effort, publicity and options in object
     * @param bool $showSensitiveDetails Leaves details about stores like contact, updatedAt, showsSticker and groceries in object
     *
     * @throws DatabaseNoValueFoundException Store not found
     */
    public function getStore(int $storeId, bool $showDetails, bool $showSensitiveDetails): Store
    {
        $suppressLoadingGroceries = !$showSensitiveDetails;
        $dbResult = $this->storeGateway->getStore($storeId, $suppressLoadingGroceries);
        $dbResult->region->name = $this->regionGateway->getRegionName($dbResult->region->id);

        if (!$showDetails) {
            $dbResult->description = null;
            $dbResult->effort = null;
            $dbResult->publicity = null;
            $dbResult->options = null;
        }

        if (!$showSensitiveDetails) {
            $dbResult->contact = null;
            $dbResult->updatedAt = null;
            $dbResult->effort = null;
            $dbResult->showsSticker = null;
            $dbResult->groceries = null;
        }

        return $dbResult;
    }

    /**
     * Creates a new store with the possiblility to post a first message.
     *
     * This method creates all required parts for a store
     * 1. Store information
     * 2. Add author to the Store as store manager
     * 3. Creates conversation threads for Team and Jumpers
     * 4. Optional a first notes to the store wall
     * 5. Informs members of the store related region about a new store
     *
     * @param CreateStoreData $createStore Initial required store information
     * @param int $authorFsId FoddsaverId of the store creator, it is used for conversation and information bell
     * @param string $firstStorePost First message on the store wall
     *
     * @throws StoreTransactionException When region is invalid or not a region (like workinggroups)
     */
    public function createStore(CreateStoreData $createStore, int $authorFsId, ?string $firstStorePost = null): int
    {
        try {
            $regionType = $this->regionGateway->getType($createStore->regionId);
        } catch (Exception $dbExpection) {
            throw new StoreTransactionException(StoreTransactionException::INVALID_REGION);
        }
        if (!UnitType::isAccessibleRegion($regionType)) {
            throw new StoreTransactionException(StoreTransactionException::INVALID_REGION_TYPE);
        }

        $store = $createStore->toStore();
        $storeId = $this->storeGateway->addStore($store);

        $this->storeGateway->addStoreManager($storeId, $authorFsId);

        $storeTeamChatId = $this->messageGateway->createConversation([$authorFsId], true);
        $this->storeGateway->updateStoreConversation($storeId, $storeTeamChatId, false);

        $standbyTeamChatId = $this->messageGateway->createConversation([$authorFsId], true);
        $this->storeGateway->updateStoreConversation($storeId, $standbyTeamChatId, true);

        $this->setStoreNameInConversations($storeId, $createStore->name);

        $this->storeGateway->add_betrieb_notiz([
            'foodsaver_id' => $authorFsId,
            'betrieb_id' => $storeId,
            'text' => '{BETRIEB_ADDED}',
            'zeit' => date('Y-m-d H:i:s', time() - 10),
            'milestone' => Milestone::CREATED,
        ]);

        if (!empty($firstStorePost)) {
            $this->storeGateway->add_betrieb_notiz([
                'foodsaver_id' => $authorFsId,
                'betrieb_id' => $storeId,
                'text' => $firstStorePost,
                'zeit' => date('Y-m-d H:i:s'),
                'milestone' => Milestone::NONE,
            ]);
        }

        $authorName = $this->foodsaverGateway->getFoodsaverName($authorFsId);
        $foodsaver = $this->foodsaverGateway->getFoodsaversByRegion($createStore->regionId);

        $bellData = Bell::create('store_new_title', 'store_new', 'fas fa-store-alt', [
            'href' => '/?page=fsbetrieb&id=' . $storeId
        ], [
            'user' => $authorName,
            'name' => $createStore->name
        ], BellType::createIdentifier(BellType::NEW_STORE, $storeId));
        $this->bellGateway->addBell(
            array_map(
                function (Profile $f) { return $f->id; },
                $foodsaver
            ),
            $bellData
        );

        return $storeId;
    }

    /**
     * Update the information about the store.
     *
     * @param int $storeId Identifier of store to modify
     * @param PatchStore $storeChange Changes on the store
     *
     * @return bool information have changed
     *
     * @throws StoreTransactionException
     */
    public function updateStore(int $storeId, PatchStore $storeChange): bool
    {
        $store = $this->storeGateway->getStore($storeId);

        $changeInformation = $this->checkAndPatchStore($storeChange, $store);

        if ($changeInformation->informationChanged) {
            $store->updatedAt = Carbon::now();
            $this->storeGateway->updateStoreData($store, $changeInformation->groceriesChanged);

            if ($changeInformation->nameChanged) {
                $this->setStoreNameInConversations($storeId, $store->name);
            }
        }

        return $changeInformation->informationChanged;
    }

    /**
     * Check for the correct values of the store and updates the store object.
     *
     * @param PatchStore $storeChange Changes on the store
     * @param Store $store Store to apply changes
     *
     * @return PatchStoreChangeInformation information about the important changes
     *
     * @throws StoreTransactionException
     */
    private function checkAndPatchStore(PatchStore &$storeChange, Store &$store): PatchStoreChangeInformation
    {
        $changeInformation = new PatchStoreChangeInformation();
        if (!empty($storeChange->name)) {
            $changeInformation->informationChanged = true;
            $changeInformation->nameChanged = true;
            $store->name = $storeChange->name;
        }

        if (!empty($storeChange->regionId)) {
            $changeInformation->informationChanged = true;
            $store->region = MinimalRegionIdentifier::createFromId($storeChange->regionId);
        }

        if (!empty($storeChange->publicInfo)) {
            $changeInformation->informationChanged = true;
            $store->publicInfo = $this->sanitizerService->purifyHtml($storeChange->publicInfo);
        }

        if (!is_null($storeChange->publicTime)) {
            $publicTime = PublicTimes::tryFrom($storeChange->publicTime);
            if (!$publicTime) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_PUBLIC_TIMES);
            }
            $changeInformation->informationChanged = true;
            $store->publicTime = $publicTime;
        }

        if (!is_null($storeChange->categoryId)) {
            $changeInformation->informationChanged = true;
            if ($storeChange->categoryId !== 0) {
                $storeCategoryExists = $this->storeGateway->existStoreCategory($storeChange->categoryId);
                if (!$storeCategoryExists) {
                    throw new StoreTransactionException(StoreTransactionException::STORE_CATEGORY_NOT_EXISTS);
                }
                $store->category = MinimalIdentifier::createFromId($storeChange->categoryId);
            } else {
                $store->category = null;
            }
        }

        if (!is_null($storeChange->chainId)) {
            $changeInformation->informationChanged = true;
            if ($storeChange->chainId !== 0) {
                $storeChainExists = $this->storeGateway->existStoreChain($storeChange->chainId);
                if (!$storeChainExists) {
                    throw new StoreTransactionException(StoreTransactionException::STORE_CHAIN_NOT_EXISTS);
                }
                $store->chain = MinimalIdentifier::createFromId($storeChange->chainId);
            } else {
                $store->chain = null;
            }
        }

        if (!is_null($storeChange->cooperationStatus)) {
            $cooperationStatus = CooperationStatus::tryFrom($storeChange->cooperationStatus);
            if (!$cooperationStatus) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_COOPERATION_STATUS);
            }
            $changeInformation->informationChanged = true;
            $store->cooperationStatus = $cooperationStatus;
        }

        if (!empty($storeChange->description)) {
            $changeInformation->informationChanged = true;
            $store->description = $storeChange->description;
        }

        if (!empty($storeChange->cooperationStart)) {
            $cooperationStart = DateTime::createFromFormat('Y-m-d', $storeChange->cooperationStart);
            if (!$cooperationStart) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_STORE_COOPERATION_START);
            }
            $changeInformation->informationChanged = true;
            $store->cooperationStart = $cooperationStart;
        }

        if ($storeChange->teamStatus !== null) {
            if (!TeamSearchStatus::tryFrom($storeChange->teamStatus)) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_STORE_TEAM_STATUS);
            }
            $changeInformation->informationChanged = true;
            $store->teamStatus = TeamSearchStatus::from($storeChange->teamStatus);
        }

        if (!is_null($storeChange->calendarInterval)) {
            $changeInformation->informationChanged = true;
            $store->calendarInterval = $storeChange->calendarInterval;
        }

        if (!is_null($storeChange->weight)) {
            $changeInformation->informationChanged = true;
            $store->weight = $storeChange->weight;
        }

        if (!is_null($storeChange->effort)) {
            $effort = ConvinceStatus::tryFrom($storeChange->effort);
            if (!$effort) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_CONVINCE_STATUS);
            }
            $changeInformation->informationChanged = true;
            $store->effort = $effort;
        }

        if (!is_null($storeChange->showsSticker)) {
            $changeInformation->informationChanged = true;
            $store->showsSticker = $storeChange->showsSticker;
        }

        if (!is_null($storeChange->publicity)) {
            $changeInformation->informationChanged = true;
            $store->publicity = $storeChange->publicity;
        }

        if (!is_null($storeChange->groceries)) {
            $changeInformation->informationChanged = true;
            $changeInformation->groceriesChanged = true;
            $store->groceries = $storeChange->groceries;
        }

        if (!is_null($storeChange->location)) {
            $changed = PatchGeoLocation::apply($storeChange->location, $store->location);
            if ($changed) {
                $changeInformation->informationChanged = true;
            }
        }

        if (!is_null($storeChange->address)) {
            $changed = PatchAddress::apply($storeChange->address, $store->address);
            if ($changed) {
                $changeInformation->informationChanged = true;
            }
        }

        if (!is_null($storeChange->contact)) {
            $changed = PatchContactData::apply($storeChange->contact, $store->contact);
            if ($changed) {
                $changeInformation->informationChanged = true;
            }
        }

        if (!is_null($storeChange->options)) {
            $changed = PatchStoreOptionModel::apply($storeChange->options, $store->options);
            if ($changed) {
                $changeInformation->informationChanged = true;
            }
        }

        return $changeInformation;
    }

    /**
     * Creates or updates a manual pick up.
     *
     * @param int $storeId Store to update
     * @param OneTimePickup $pickup Details of the pickup
     *
     * @return bool true if a new one is created, false if it is updated
     *
     * @throws PickupValidationException Exception if input is invalid
     */
    public function createOrUpdatePickup(int $storeId, OneTimePickup $pickup): bool
    {
        if ($pickup->date < Carbon::now()) {
            throw new PickupValidationException(PickupValidationException::PICK_UP_DATE_IN_THE_PAST);
        }

        if ($pickup->slots < 0 || $pickup->slots > self::MAX_SLOTS_PER_PICKUP) {
            throw new PickupValidationException(PickupValidationException::SLOT_COUNT_OUT_OF_RANGE);
        }

        if (!is_null($pickup->description) && mb_strlen($pickup->description) > self::MAX_PICKUP_DESCRIPTION_LENGTH) {
            throw new PickupValidationException(PickupValidationException::DESCRIPTION_OVERSIZED);
        }

        $occupiedSlots = count($this->pickupGateway->getPickupSignUpsForDate($storeId, $pickup->date));
        if ($pickup->slots < $occupiedSlots) {
            throw new PickupValidationException(PickupValidationException::MORE_OCCUPIED_SLOTS);
        }

        if (!$this->storeGateway->storeExists($storeId)) {
            throw new PickupValidationException(PickupValidationException::INVALID_STORE);
        }

        $filledOnetimeSlots = $this->pickupGateway->getOnetimePickups($storeId, $pickup->date);
        if ($filledOnetimeSlots) {
            $this->pickupGateway->updateOnetimePickupTotalSlots($storeId, $pickup);

            return false;
        }

        $this->pickupGateway->addOnetimePickup($storeId, $pickup);

        return true;
    }

    /**
     * Checks whether there are slots available to sign into for one specific pickupDate in a store.
     *
     * @param ?int $fsId Check whether this specific user could sign into a slot for this date
     *
     * @return ?OneTimePickup null if no available slot, else the pickup for this date
     */
    public function getPickupIfPickupSlotAvailable(int $storeId, Carbon $pickupDate, ?int $fsId = null): ?OneTimePickup
    {
        // do not allow signing up for past pickups
        if ($pickupDate < Carbon::now()) {
            return null;
        }

        $pickupSlots = $this->pickupGateway->getPickupSlots($storeId, $pickupDate, $pickupDate, $pickupDate);

        // expect exactly one pickup for this "range" query
        if (count($pickupSlots) === 1) {
            $pickup = $pickupSlots[0];
        } else {
            return null;
        }

        // check if there are any free slots
        if (!$pickup['isAvailable']) {
            return null;
        }

        // when a user is provided, that user must not already be signed up
        if ($fsId) {
            $signedUpFoodsaverIds = array_column($pickup['occupiedSlots'], 'foodsaverId');
            if (in_array($fsId, $signedUpFoodsaverIds)) {
                return null;
            }
        }

        $pickupObj = new OneTimePickup();
        $pickupObj->date = $pickupDate;
        $pickupObj->slots = $pickup['totalSlots'];
        $pickupObj->description = $pickup['description'];

        return $pickupObj;
    }

    /**
     * Returns the time of the next available pickup slot or null if none is available up to the
     * given maximum date.
     *
     * @param Carbon $maxDate end of date range
     *
     * @return ?DateTime the slot's time or null
     */
    public function getNextAvailablePickupTime(int $storeId, Carbon $maxDate): ?DateTime
    {
        if ($maxDate < Carbon::now()) {
            return null;
        }

        $pickupSlots = $this->pickupGateway->getPickupSlots($storeId, Carbon::now(), $maxDate, $maxDate);

        $minimumDate = null;
        foreach ($pickupSlots as $slot) {
            if ($slot['isAvailable'] && (is_null($minimumDate) || $slot['date'] < $minimumDate)) {
                $minimumDate = $slot['date'];
            }
        }

        return $minimumDate;
    }

    /**
     * Returns the available pickup status of a store: 1, 2, or 3 if there is a free pickup slot in the next day,
     * three days, or five days, respectively. Returns 0 if there is no free slot in the next five days.
     */
    public function getAvailablePickupStatus(int $storeId): int
    {
        $availableDate = $this->getNextAvailablePickupTime($storeId, Carbon::tomorrow()->addDays(5));
        if (is_null($availableDate)) {
            return self::STATUS_GREEN;
        } elseif ($availableDate < Carbon::tomorrow()->addDay()) {
            return self::STATUS_RED_TODAY_TOMORROW;
        } elseif ($availableDate < Carbon::tomorrow()->addDays(3)) {
            return self::STATUS_ORANGE_3_DAYS;
        } else {
            return self::STATUS_YELLOW_5_DAYS;
        }
    }

    public function joinPickup(int $storeId, Carbon $date, int $fsId, int $issuerId = null): bool
    {
        if ($fsId != $issuerId) {
            /* currently it is forbidden to add other users to a pickup */
            throw new StoreTransactionException(StoreTransactionException::NO_PICKUP_OTHER_USER);
        }

        $confirmed = $this->pickupIsPreconfirmed($storeId, $issuerId);

        /* Never occupy more slots than available */
        if ($pickup = $this->getPickupIfPickupSlotAvailable($storeId, $date, $fsId)) {
            if ($this->checkPickupRule($storeId, $date, $fsId)) {
                $this->pickupGateway->addFetcher($fsId, $storeId, $date, $confirmed);
                // [#860] convert to manual slot, so they don't vanish when changing the schedule
                $this->createOrUpdatePickup($storeId, $pickup);
            } else {
                throw new \DomainException('District Pickup Rule violated');
            }
        } else {
            throw new StoreTransactionException(StoreTransactionException::NO_PICKUP_SLOT_AVAILABLE);
        }

        $this->storeGateway->addStoreLog($storeId, $fsId, null, $date, StoreLogAction::SIGN_UP_SLOT);

        return $confirmed;
    }

    private function pickupIsPreconfirmed(int $storeId, int $issuerId = null): bool
    {
        if ($issuerId) {
            return $this->storeGateway->getUserTeamStatus($issuerId, $storeId) === TeamStatus::Coordinator;
        }

        return false;
    }

    public function setStoreNameInConversations(int $storeId, string $storeName): void
    {
        if ($tcid = $this->storeGateway->getBetriebConversation($storeId, false)) {
            $teamConversationName = $this->translator->trans('store.team_conversation_name', ['{name}' => $storeName]);
            $this->messageGateway->renameConversation($tcid, $teamConversationName);
        }
        if ($scid = $this->storeGateway->getBetriebConversation($storeId, true)) {
            $springerConversationName = $this->translator->trans('store.springer_conversation_name', ['{name}' => $storeName]);
            $this->messageGateway->renameConversation($scid, $springerConversationName);
        }
    }

    /**
     * @return StoreStatusForMember[]
     */
    public function listAllStoreStatusForFoodsaver(?int $foodsaverId, ?bool $activeStores = true): array
    {
        if ($foodsaverId === null) {
            return [];
        }
        $results = $this->storeGateway->listAllStoreTeamMembershipsForFoodsaver($foodsaverId, $activeStores ? StoreTransactions::DEFAULT_USER_SHOWN_STORE_COOPERATION_STATE : []);
        $storeTeamMemberships = [];
        foreach ($results as $resultRow) {
            $item = new StoreStatusforMember();
            $item->store = $resultRow->store;
            $item->isManaging = $resultRow->isManaging;
            $item->membershipStatus = $resultRow->membershipStatus;
            if ($item->membershipStatus == MembershipStatus::MEMBER) {
                // add info about the next free pickup slot to the store
                $item->pickupStatus = $this->getAvailablePickupStatus($item->store->id);
            }

            $storeTeamMemberships[] = $item;
        }

        return $storeTeamMemberships;
    }

    public function requestStoreTeamMembership(int $storeId, int $userId): void
    {
        $this->storeGateway->addStoreRequest($storeId, $userId);

        $this->storeGateway->addStoreLog($storeId, $userId, null, null, StoreLogAction::REQUEST_TO_JOIN);

        $this->notifyStoreManagersAboutRequest($storeId, $userId);
    }

    /**
     * Accepts a user's request to join a store, and moves the user to the standby team if desired.
     * This creates a bell notification for that user, adds an entry to the store log,
     * and makes sure the user is in the store's region.
     *
     * @param bool $moveToStandby if true, place the new member on the standby list instead of the regular store team
     */
    public function acceptStoreRequest(int $storeId, int $userId, bool $moveToStandby = false): void
    {
        $this->addUserToStore($storeId, $userId, $moveToStandby);

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::REQUEST_APPROVED);

        $actionType = $moveToStandby ? StoreLogAction::MOVED_TO_JUMPER : StoreLogAction::REQUEST_APPROVED;
        $this->triggerBellForJoining($storeId, $userId, $actionType);

        // add the user to the store's region
        $regionId = $this->storeGateway->getStoreRegionId($storeId);
        $this->regionGateway->linkBezirk($userId, $regionId);
    }

    /**
     * Rejects (denies) a user's request for a store and creates a bell notification for that user.
     */
    public function declineStoreRequest(int $storeId, int $userId): void
    {
        $this->storeGateway->removeUserFromTeam($storeId, $userId);

        // userId = affected user, sessionId = active user
        // => don't add a bell notification if the request was withdrawn by the user
        if ($userId !== $this->session->id()) {
            $this->triggerBellForJoining($storeId, $userId, StoreLogAction::REQUEST_DECLINED);
        }
    }

    public function createKickMessage(int $foodsaverId, int $storeId, DateTime $pickupDate, ?string $message = null): string
    {
        $fs = $this->foodsaverGateway->getFoodsaver($foodsaverId);
        $storeName = $this->storeGateway->getStoreName($storeId);

        $salutation = $this->translator->trans('salutation.' . $fs['geschlecht']) . ' ' . $fs['name'];
        $mandatoryMessage = $this->translator->trans('pickup.kick_message', [
            '{storeName}' => $storeName,
            '{date}' => date('d.m.Y H:i', $pickupDate->getTimestamp())
        ]);
        $optionalMessage = empty($message) ? '' : ("\n\n" . $message);
        $footer = $this->translator->trans('pickup.kick_message_footer');

        return $salutation . ",\n" . $mandatoryMessage . $optionalMessage . "\n\n" . $footer;
    }

    public function addStoreMember(int $storeId, int $userId, bool $moveToStandby = false): void
    {
        $this->addUserToStore($storeId, $userId, $moveToStandby);

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::ADDED_WITHOUT_REQUEST);

        $this->triggerBellForJoining($storeId, $userId, StoreLogAction::ADDED_WITHOUT_REQUEST);
    }

    public function removeStoreMember(int $storeId, int $userId): void
    {
        $this->pickupGateway->deleteAllDatesFromAFoodsaver($userId, $storeId);
        $this->storeGateway->removeUserFromTeam($storeId, $userId);

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::REMOVED_FROM_STORE);

        if ($teamChatConversationId = $this->storeGateway->getBetriebConversation($storeId)) {
            $this->messageGateway->deleteUserFromConversation($teamChatConversationId, $userId);
        }

        if ($jumperChatConversationId = $this->storeGateway->getBetriebConversation($storeId, true)) {
            $this->messageGateway->deleteUserFromConversation($jumperChatConversationId, $userId);
        }
    }

    public function leaveAllStoreTeams(int $userId): void
    {
        $ownStoreIds = $this->storeGateway->listStoreIds($userId);

        foreach ($ownStoreIds as $storeId) {
            $this->removeStoreMember($storeId, $userId);
        }
    }

    public function moveMemberToStandbyTeam(int $storeId, int $userId): void
    {
        $this->storeGateway->setUserMembershipStatus($storeId, $userId, MembershipStatus::JUMPER);

        $standbyTeamChatId = $this->storeGateway->getBetriebConversation($storeId, true);
        if ($standbyTeamChatId) {
            $this->messageGateway->addUserToConversation($standbyTeamChatId, $userId);
        }

        $teamChatId = $this->storeGateway->getBetriebConversation($storeId);
        if ($teamChatId) {
            $this->messageGateway->deleteUserFromConversation($teamChatId, $userId);
        }

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::MOVED_TO_JUMPER);
    }

    public function moveMemberToRegularTeam(int $storeId, int $userId): void
    {
        $this->storeGateway->setUserMembershipStatus($storeId, $userId, MembershipStatus::MEMBER);

        $teamChatId = $this->storeGateway->getBetriebConversation($storeId);
        if ($teamChatId) {
            $this->messageGateway->addUserToConversation($teamChatId, $userId);
        }

        $standbyTeamChatId = $this->storeGateway->getBetriebConversation($storeId, true);
        if ($standbyTeamChatId) {
            $this->messageGateway->deleteUserFromConversation($standbyTeamChatId, $userId);
        }

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::MOVED_TO_TEAM);
    }

    public function makeMemberResponsible(int $storeId, int $userId): void
    {
        $this->storeGateway->addStoreManager($storeId, $userId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::APPOINT_STORE_MANAGER);

        $standbyTeamChatId = $this->storeGateway->getBetriebConversation($storeId, true);
        if ($standbyTeamChatId) {
            $this->messageGateway->addUserToConversation($standbyTeamChatId, $userId);
        }
    }

    public function downgradeResponsibleMember(int $storeId, int $userId): void
    {
        /* check if other managers exist (cannot leave as last manager) */
        $this->storeGateway->removeStoreManager($storeId, $userId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::REMOVED_AS_STORE_MANAGER);

        $standbyTeamChatId = $this->storeGateway->getBetriebConversation($storeId, true);
        if ($standbyTeamChatId) {
            $this->messageGateway->deleteUserFromConversation($standbyTeamChatId, $userId);
        }
    }

    private function addUserToStore(int $storeId, int $userId, bool $moveToStandby): void
    {
        $this->storeGateway->addUserToTeam($storeId, $userId);

        if ($moveToStandby) {
            $this->moveMemberToStandbyTeam($storeId, $userId);
        } else {
            $this->moveMemberToRegularTeam($storeId, $userId);
        }

        $this->storeGateway->add_betrieb_notiz([
            'foodsaver_id' => $userId,
            'betrieb_id' => $storeId,
            'text' => '{ACCEPT_REQUEST}',
            'zeit' => date('Y-m-d H:i:s'),
            'milestone' => Milestone::ACCEPTED,
        ]);
    }

    // notify people who can do something with the request: store managers, region ambassadors, or orga
    private function notifyStoreManagersAboutRequest(int $storeId, int $userId): void
    {
        $bellRecipients = $this->storeGateway->getBiebsForStore($storeId);
        if (!$bellRecipients) {
            $regionId = $this->storeGateway->getStoreRegionId($storeId);
            $ambassadors = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);

            if ($ambassadors) {
                $bellRecipients = array_column($ambassadors, 'id');
            } else {
                $bellRecipients = $this->foodsaverGateway->getOrgaTeam();
            }
        }

        $storeName = $this->storeGateway->getStoreName($storeId);

        $bellData = Bell::create('store_new_request_title', 'store_new_request', 'fas fa-user-plus', [
            'href' => '/store/' . $storeId . '?showTeamRequests',
        ], [
            'user' => $this->session->user('name'),
            'name' => $storeName,
        ], BellType::createIdentifier(BellType::NEW_STORE_REQUEST, $storeId));

        $this->bellGateway->addBell($bellRecipients, $bellData);
    }

    private function triggerBellForJoining(int $storeId, int $userId, int $actionType): void
    {
        if ($actionType === StoreLogAction::ADDED_WITHOUT_REQUEST) {
            $bellTitle = 'store_request_imposed_title';
            $bellMsg = 'store_request_imposed';
            $bellIcon = 'fas fa-user-plus';
            $bellId = 'store-imposed-' . $storeId . '-' . $userId;
        } elseif ($actionType === StoreLogAction::MOVED_TO_JUMPER) {
            $bellTitle = 'store_request_accept_wait_title';
            $bellMsg = 'store_request_accept_wait';
            $bellIcon = 'fas fa-user-tag';
            $bellId = BellType::createIdentifier(BellType::STORE_REQUEST_WAITING, $userId);
        } elseif ($actionType === StoreLogAction::REQUEST_APPROVED) {
            $bellTitle = 'store_request_accept_title';
            $bellMsg = 'store_request_accept';
            $bellIcon = 'fas fa-user-check';
            $bellId = BellType::createIdentifier(BellType::STORE_REQUEST_ACCEPTED, $userId);
        } elseif ($actionType === StoreLogAction::REQUEST_DECLINED) {
            $bellTitle = 'store_request_deny_title';
            $bellMsg = 'store_request_deny';
            $bellIcon = 'fas fa-user-times';
            $bellId = BellType::createIdentifier(BellType::STORE_REQUEST_REJECTED, $userId);
        } else {
            throw new \DomainException('Unknown store-team action: ' . $actionType);
        }
        $bellLink = '/?page=fsbetrieb&id=' . $storeId;

        $storeName = $this->storeGateway->getStoreName($storeId);

        $bellData = Bell::create($bellTitle, $bellMsg, $bellIcon, [
            'href' => $bellLink,
        ], [
            'user' => $this->session->user('name'),
            'name' => $storeName,
        ], $bellId);
        $this->bellGateway->addBell([$userId], $bellData);
    }

    public function triggerBellForRegularPickupChanged(int $storeId)
    {
        $storeName = $this->storeGateway->getStoreName($storeId);

        $team = $this->storeGateway->getStoreTeam($storeId);
        $team = array_map(function ($foodsaver) { return $foodsaver['id']; }, $team);
        $bellData = Bell::create('store_cr_times_title', 'store_cr_times', 'fas fa-user-clock', [
            'href' => '/?page=fsbetrieb&id=' . $storeId,
        ], [
            'user' => $this->session->user('name'),
            'name' => $storeName,
        ], BellType::createIdentifier(BellType::STORE_TIME_CHANGED, $storeId));
        $this->bellGateway->addBell($team, $bellData);
    }

    /**
     * @param int $storeId Id of Store
     * @param Carbon $pickupDate Date of Pickup
     * @param int $fsId foodsaver ID
     *
     * @return bool true or false - true if no rule is violated, false if a rule is vialated
     *
     * @throws Exception
     */
    public function checkPickupRule(int $storeId, Carbon $pickupDate, int $fsId): bool
    {
        $response['result'] = true; //default response, rule is passed

        // Does this store have a pickupRule ?
        if ($this->storeGateway->getUseRegionPickupRule($storeId)) {
            $regionId = $this->storeGateway->getStoreRegionId($storeId);
            // Does the region of the store have a pickuprule and it is active?
            if ((bool)$this->regionGateway->getRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_ACTIVE)) {
                // how many hours before a pickup can this rule be ignored ?
                $ignoreRuleHours = (int)$this->regionGateway->getRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_INACTIVE_HOURS);
                $res = Carbon::now()->diffInHours($pickupDate);
                if ($res > $ignoreRuleHours) {
                    // the allowed numbers of pickups in a timespan. Timespan is +/- from pickupdate
                    $numberAllowedPickups = (int)$this->regionGateway->getRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_LIMIT_NUMBER);
                    $intervall = (int)$this->regionGateway->getRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_TIMESPAN_DAYS);
                    // if we have more or same amount of used slots occupied then allowed we return false
                    if ($this->pickupGateway->getNumberOfPickupsForUserWithStoreRules($fsId, $pickupDate->copy()->subDays($intervall), $pickupDate->copy()->addDays($intervall)) >= $numberAllowedPickups) {
                        return false;
                    }
                    // if we have more then or same amount of allowed pickups per day we return false
                    $numberAllowedPickupsPerDay = (int)$this->regionGateway->getRegionOption($regionId, RegionOptionType::REGION_PICKUP_RULE_LIMIT_DAY_NUMBER);
                    if ($this->pickupGateway->getNumberOfPickupsForUserWithStoreRulesSameDay($fsId, $pickupDate) >= $numberAllowedPickupsPerDay) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Returns all team member of the store (active and waiting list) and makes sure that details like the phone
     * number are only included if allowed.
     *
     * @param array $store store data from the database
     * @param bool $includeUserDetails whether to include or omit phone numbers and last fetch date
     */
    private function getDisplayedStoreTeam(array $store, bool $includeUserDetails): array
    {
        $allowedFields = [
            // personal info
            'id', 'name', 'photo', 'quiz_rolle', 'sleep_status', 'verified',
            // team-related info
            'verantwortlich', 'team_active', 'stat_fetchcount', 'add_date',
        ];
        if ($includeUserDetails) {
            array_push($allowedFields, 'handy', 'telefon', 'last_fetch');
        }

        return array_map(
            function ($teamMember) use ($allowedFields) {
                return array_filter($teamMember, function ($key) use ($allowedFields) {
                    return in_array($key, $allowedFields);
                }, ARRAY_FILTER_USE_KEY);
            },
            array_merge($store['foodsaver'], $store['springer']),
        );
    }
}
