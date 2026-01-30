<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use DateTime;
use Exception;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellTransactions;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Categories\StoreCategoriesGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Store\ConvinceStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicityStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\StickerStatus;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\WallType;
use Foodsharing\Modules\Core\DTO\MinimalIdentifier;
use Foodsharing\Modules\Core\DTO\PatchGeoLocation;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\CategoryWithType;
use Foodsharing\Modules\Store\DTO\CommonLabel;
use Foodsharing\Modules\Store\DTO\CommonStoreMetadata;
use Foodsharing\Modules\Store\DTO\CreateStoreData;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PatchAddress;
use Foodsharing\Modules\Store\DTO\PatchContactData;
use Foodsharing\Modules\Store\DTO\PatchStore;
use Foodsharing\Modules\Store\DTO\PatchStoreOptionModel;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreChainInformation;
use Foodsharing\Modules\Store\DTO\StoreInvitation;
use Foodsharing\Modules\Store\DTO\StoreListInformation;
use Foodsharing\Modules\Store\DTO\StoreStatusForMember;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
use Foodsharing\Modules\WallPost\DTO\WallPost;
use Foodsharing\Modules\WallPost\WallPostGateway;
use Foodsharing\Utility\WeightHelper;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StoreTransactions
{
    final public const array DEFAULT_USER_SHOWN_STORE_COOPERATION_STATE = [
        CooperationStatus::UNCLEAR,
        CooperationStatus::NO_CONTACT,
        CooperationStatus::IN_NEGOTIATION,
        CooperationStatus::COOPERATION_ESTABLISHED
    ];

    final public const int MAX_SLOTS_PER_PICKUP = 50;
    // status constants for getAvailablePickupStatus
    private const int STATUS_RED_TODAY_TOMORROW = 3;
    private const int STATUS_ORANGE_3_DAYS = 2;
    private const int STATUS_YELLOW_5_DAYS = 1;
    private const int STATUS_GREEN = 0;
    private const int MAX_PICKUP_DESCRIPTION_LENGTH = 100;

    public const STORE_METADATA_VERSION_KEY = 'storeMetadataVersionKey';
    public const STORE_METADATA_KEY = 'storeMetadataKey';
    public const STORE_METADATA_INTERVAL = 86400; // 1 day

    public function __construct(
        private readonly MessageGateway $messageGateway,
        private readonly PickupGateway $pickupGateway,
        private readonly StoreGateway $storeGateway,
        private readonly TranslatorInterface $translator,
        private readonly BellGateway $bellGateway,
        private readonly BellTransactions $bellTransactions,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionGateway $regionGateway,
        private readonly StoreCategoriesGateway $storeCategoriesGateway,
        private readonly StoreChainGateway $storeChainGateway,
        private readonly WallPostGateway $wallPostGateway,
        private readonly MessageTransactions $messageTransactions,
        private readonly Session $session,
        private readonly Mem $mem,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Returns a store's data including the team members in a format suitable for the frontend.
     *
     * @param int $storeId the store
     * @param bool $includeUserDetails whether to include phone numbers and last fetch dates for the team members
     */
    public function getMyStoreTeam(int $storeId, bool $includeUserDetails, bool $includeDistance): array
    {
        $location = null;
        if ($includeDistance) {
            $store = $this->storeGateway->getStore($storeId, true);
            $location = $store->location;
        }
        $members = $this->storeGateway->getStoreTeam($storeId, [MembershipStatus::MEMBER, MembershipStatus::JUMPER], $includeDistance, $location);

        return $this->getDisplayedStoreTeam($members, $includeUserDetails, $includeDistance);
    }

    /**
     * Get store applications for a specific user and store.
     *
     * @param int $storeId  the ID of the store
     *
     * @return array an array containing store requests
     */
    public function getStoreApplications(int $storeId): array
    {
        $store = $this->storeGateway->getStore($storeId);
        try {
            return $this->storeGateway->getApplications($storeId, $store->location);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getCommonStoreMetadata(): CommonStoreMetadata
    {
        $store = new CommonStoreMetadata();

        $store->groceries = array_map(fn ($row) => CommonLabel::createFromArray($row), $this->storeGateway->getBasics_groceries());

        $store->categories = $this->storeCategoriesGateway->getCategories();
        $store->categories[] = new CategoryWithType(0, $this->translator->trans('store.nodeclaration'));

        $store->status = array_map(fn ($row) => CommonLabel::createFromArray($row), [
            ['id' => CooperationStatus::UNCLEAR->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => CooperationStatus::NO_CONTACT->value, 'name' => $this->translator->trans('storestatus.1')],
            ['id' => CooperationStatus::IN_NEGOTIATION->value, 'name' => $this->translator->trans('storestatus.2')],
            ['id' => CooperationStatus::DOES_NOT_WANT_TO_WORK_WITH_US->value, 'name' => $this->translator->trans('storestatus.4')],
            ['id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'name' => $this->translator->trans('storestatus.5')],
            ['id' => CooperationStatus::GIVES_TO_OTHER_CHARITY->value, 'name' => $this->translator->trans('storestatus.6')],
            ['id' => CooperationStatus::PERMANENTLY_CLOSED->value, 'name' => $this->translator->trans('storestatus.7')],
        ]);

        $store->publicTimes = array_map(fn ($row) => CommonLabel::createFromArray($row), [
            ['id' => PublicTimes::NOT_SET->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => PublicTimes::IN_THE_MORNING->value, 'name' => $this->translator->trans('storeview.public_time_in_the_morning')],
            ['id' => PublicTimes::AT_NOON_IN_THE_AFTERNOON->value, 'name' => $this->translator->trans('storeview.public_time_at_noon_or_afternoon')],
            ['id' => PublicTimes::IN_THE_EVENING->value, 'name' => $this->translator->trans('storeview.public_time_in_the_evening')],
            ['id' => PublicTimes::AT_NIGHT->value, 'name' => $this->translator->trans('storeview.public_time_at_night')]
        ]);

        $store->convinceStatus = array_map(fn ($row) => CommonLabel::createFromArray($row), [
            ['id' => ConvinceStatus::NOT_SET->value, 'name' => $this->translator->trans('store.nodeclaration')],
            ['id' => ConvinceStatus::NO_PROBLEM_AT_ALL->value, 'name' => $this->translator->trans('store.convince.none')],
            ['id' => ConvinceStatus::AFTER_SOME_PERSUASION->value, 'name' => $this->translator->trans('store.convince.some')],
            ['id' => ConvinceStatus::DIFFICULT_NEGOTIATION->value, 'name' => $this->translator->trans('store.convince.much')],
            ['id' => ConvinceStatus::LOOKED_BAD_BUT_WORKED->value, 'name' => $this->translator->trans('store.convince.final')]
        ]);

        $store->storeChains = [new CommonLabel(0, $this->translator->trans('store.nodeclaration')),
            ...array_map(fn ($row) => CommonLabel::createFromArray($row), $this->storeGateway->getBasics_chain())];

        $store->weight = array_map(fn ($row) => CommonLabel::createFromArray($row), (new WeightHelper())->getWeightListEntries());

        return $store;
    }

    public function getCommonStoreMetadataFromCache(bool $supressStoreChains, int $currentVersion): CommonStoreMetadata
    {
        $metadata = $this->cache->get(self::STORE_METADATA_KEY, function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::STORE_METADATA_INTERVAL); // just in case someone forgets to invalidate the cache at some point

            return $this->getCommonStoreMetadata();
        });
        $metadata->version = $currentVersion;
        if ($supressStoreChains) {
            $metadata->storeChains = null;
        }

        return $metadata;
    }

    public function invalidateCachedStoreMetadata(): void
    {
        $this->cache->delete(self::STORE_METADATA_KEY);
        $currentVersion = (int)$this->mem->get(self::STORE_METADATA_VERSION_KEY);
        $this->mem->set(self::STORE_METADATA_VERSION_KEY, ++$currentVersion);
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

        if ($dbResult->chain) {
            $chainDetails = $this->storeChainGateway->getChainInformationForStore($dbResult->chain->id);
            $dbResult->chain->name = $chainDetails['name'];
            $dbResult->chain->information = $chainDetails['common_store_information'];
            $dbResult->chain->kams = $this->storeChainGateway->getStoreChainKeyAccountManagers($dbResult->chain->id);
        }

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
        } catch (Exception) {
            throw new StoreTransactionException(StoreTransactionException::INVALID_REGION);
        }
        if (!UnitType::isAccessibleRegion($regionType)) {
            throw new StoreTransactionException(StoreTransactionException::INVALID_REGION_TYPE);
        }

        $storeTeamChatId = $this->messageGateway->createConversation([$authorFsId], true);
        $standbyTeamChatId = $this->messageGateway->createConversation([$authorFsId], true);

        $store = $createStore->toStore();
        $storeId = $this->storeGateway->addStore($store, $storeTeamChatId, $standbyTeamChatId);

        $this->storeGateway->addStoreManager($storeId, $authorFsId);

        $this->setStoreNameInConversations($storeId, $createStore->name);

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
                fn (Profile $f) => $f->id,
                $foodsaver
            ),
            $bellData
        );
        if ($firstStorePost) {
            $wallpost = new WallPost();
            $wallpost->body = $firstStorePost;
            $this->wallPostGateway->addPost($wallpost, $authorFsId, WallType::STORE, $storeId);
        }

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

        if ($changeInformation->chainChanged && $store->chain) {
            $kams = $this->storeChainGateway->getStoreChainKeyAccountManagers($store->chain->id);
            $chain = $this->storeChainGateway->getChainInformationForStore($store->chain->id);

            $bell = Bell::create(
                'store_added_to_chain_title',
                'store_added_to_chain',
                'fas fa-chain',
                ['href' => '/store/' . $store->id],
                ['chain' => $chain['name'], 'store' => $store->name],
                BellType::createIdentifier(BellType::STORE_ADDED_TO_CHAIN, $store->chain->id, $store->id),
            );
            $this->bellGateway->delBellsByIdentifier($bell->identifier);
            $this->bellGateway->addBellForUsers(array_map(fn ($kam) => $kam->id, $kams), $bell);
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
            $store->region = MinimalRegionIdentifier::create($storeChange->regionId);
        }

        if ($storeChange->publicInfo != $store->publicInfo) {
            $changeInformation->informationChanged = true;
            $store->publicInfo = $storeChange->publicInfo;
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
                $storeCategoryExists = $this->storeCategoriesGateway->categoryExists($storeChange->categoryId);
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
            $changeInformation->chainChanged = $store->chain ? $store->chain->id !== $storeChange->chainId : true;
            if ($storeChange->chainId !== 0) {
                $storeChainExists = $this->storeGateway->existStoreChain($storeChange->chainId);
                if (!$storeChainExists) {
                    throw new StoreTransactionException(StoreTransactionException::STORE_CHAIN_NOT_EXISTS);
                }
                $store->chain = StoreChainInformation::createFromId($storeChange->chainId);
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
            $sticker = StickerStatus::tryFrom($storeChange->showsSticker);
            if (!$sticker) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_CONVINCE_STATUS);
            }
            $changeInformation->informationChanged = true;
            $store->showsSticker = $sticker;
        }

        if (!is_null($storeChange->publicity)) {
            $publicity = PublicityStatus::tryFrom($storeChange->publicity);
            if (!$publicity) {
                throw new StoreTransactionException(StoreTransactionException::INVALID_PUBLICITY_STATUS);
            }
            $changeInformation->informationChanged = true;
            $store->publicity = $publicity;
        }

        if (!is_null($storeChange->isHygieneRequired)) {
            $changeInformation->informationChanged = true;
            $store->isHygieneRequired = $storeChange->isHygieneRequired;
        }

        if (!is_null($storeChange->isVerifiedRequired)) {
            $changeInformation->informationChanged = true;
            $store->isVerifiedRequired = $storeChange->isVerifiedRequired;
        }

        if (!is_null($storeChange->isPhoneRequired)) {
            $changeInformation->informationChanged = true;
            $store->isPhoneRequired = $storeChange->isPhoneRequired;
        }

        if (!is_null($storeChange->isApplyTextRequired)) {
            $changeInformation->informationChanged = true;
            $store->isApplyTextRequired = $storeChange->isApplyTextRequired;
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

        if (!is_null($pickup->description) && mb_strlen((string)$pickup->description) > self::MAX_PICKUP_DESCRIPTION_LENGTH) {
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

    public function joinPickup(int $storeId, Carbon $date, int $userId): bool
    {
        $confirmed = $this->pickupIsPreconfirmed($storeId, $userId);

        /* Never occupy more slots than available */
        if ($pickup = $this->getPickupIfPickupSlotAvailable($storeId, $date, $userId)) {
            if ($this->checkPickupRule($storeId, $date, $userId)) {
                $this->pickupGateway->addFetcher($userId, $storeId, $date, $confirmed);
                // [#860] convert to manual slot, so they don't vanish when changing the schedule
                $this->createOrUpdatePickup($storeId, $pickup);
            } else {
                throw new \DomainException('District Pickup Rule violated');
            }
        } else {
            throw new StoreTransactionException(StoreTransactionException::NO_PICKUP_SLOT_AVAILABLE);
        }

        $this->storeGateway->addStoreLog($storeId, $userId, null, $date, StoreLogAction::SIGN_UP_SLOT);

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
            $item = new StoreStatusForMember();
            $item->store = $resultRow->store;
            $item->isManaging = $resultRow->isManaging;
            $item->membershipStatus = $resultRow->membershipStatus;
            if ($item->membershipStatus == MembershipStatus::MEMBER) {
                // add info about the next free pickup slot to the store
                $item->pickupStatus = $this->getAvailablePickupStatus($item->store->id);
            }
            $item->categoryType = $resultRow->categoryType;
            $storeTeamMemberships[] = $item;
        }

        return $storeTeamMemberships;
    }

    public function requestStoreTeamMembership(int $storeId, int $userId, ?string $message): void
    {
        $this->storeGateway->addStoreRequest($storeId, $userId);

        $this->storeGateway->addStoreLog($storeId, $userId, null, null, StoreLogAction::REQUEST_TO_JOIN, $message);

        $this->notifyStoreManagersAboutRequest($storeId);
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
    public function declineStoreRequest(int $storeId, int $userId, ?string $message): void
    {
        $this->storeGateway->removeUserFromTeam($storeId, $userId);

        // userId = affected user, sessionId = active user
        // => don't add a bell notification if the request was withdrawn by the user
        if ($userId !== $this->session->id()) {
            $this->triggerBellForJoining($storeId, $userId, StoreLogAction::REQUEST_DECLINED);
        }

        $this->messageTransactions->sendRequiredMessageToUser($userId, $this->session->id(), 'decline_store_application', $message, [
            '{storeId}' => $storeId,
            '{store}' => $this->storeGateway->getStoreName($storeId),
        ]);
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

    public function inviteStoreMember(int $storeId, int $userId): StoreInvitation
    {
        $this->storeGateway->addStoreInvitation($storeId, $userId);

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::INVITED_TO_TEAM);

        $this->triggerBellForJoining($storeId, $userId, StoreLogAction::INVITED_TO_TEAM);

        $invitation = new StoreInvitation();
        $user = $this->foodsaverGateway->getFoodsaverDetails($userId);
        $invitation->user = new Profile($user);
        $invitation->inviter = $this->foodsaverGateway->getProfile($this->session->id());
        $invitation->date = Carbon::now();
        $invitation->verified = boolval($user['verified']);

        return $invitation;
    }

    public function withdrawStoreTeamInvitation(int $storeId, int $userId): void
    {
        $this->storeGateway->removeStoreInvitation($storeId, $userId);

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::INVITATION_WITHDRAWN);

        $this->bellGateway->deleteBellsForFoodsaversByIdentifier([$userId], BellType::createIdentifier(BellType::STORE_INVITATION, $storeId));
    }

    public function acceptStoreTeamInvitation(int $storeId, int $userId): void
    {
        $this->storeGateway->addUserToTeam($storeId, $userId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::INVITATION_ACCEPTED);

        // add the user to the store's team conversation
        $teamChatId = $this->storeGateway->getBetriebConversation($storeId);
        if ($teamChatId) {
            $this->messageGateway->addUserToConversation($teamChatId, $userId);
        }

        $bellRecipients = $this->storeGateway->getBiebsForStore($storeId);
        $baseBell = Bell::create(
            'store_invitation_accepted_title',
            'store_invitation_accepted',
            'fas fa-user-plus',
            ['href' => '/store/' . $storeId . '?showInvitations'], [
                'user' => $this->session->user('name'),
                'store' => $this->storeGateway->getStoreName($storeId),
            ],
            BellType::createIdentifier(BellType::STORE_INVITATION_ACCEPTED, $storeId)
        );
        $this->bellGateway->deleteBellsForFoodsaversByIdentifier([$userId], BellType::createIdentifier(BellType::STORE_INVITATION, $storeId));
        $this->bellTransactions->addGroupedBellEvent(array_column($bellRecipients, 'id'), $baseBell, $storeId);
    }

    public function declineStoreTeamInvitation(int $storeId, int $userId): void
    {
        $this->storeGateway->removeUserFromTeam($storeId, $userId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::INVITATION_DECLINED);

        $bellRecipients = $this->storeGateway->getBiebsForStore($storeId);
        $baseBell = Bell::create(
            'store_invitation_declined_title',
            'store_invitation_declined',
            'fas fa-user-slash',
            ['href' => '/store/' . $storeId . '?showInvitations'], [
                'user' => $this->session->user('name'),
                'store' => $this->storeGateway->getStoreName($storeId),
            ],
            BellType::createIdentifier(BellType::STORE_INVITATION_DECLINED, $storeId)
        );

        $this->bellGateway->deleteBellsForFoodsaversByIdentifier([$userId], BellType::createIdentifier(BellType::STORE_INVITATION, $storeId));
        $this->bellTransactions->addGroupedBellEvent(array_column($bellRecipients, 'id'), $baseBell, $storeId);
    }

    public function removeStoreMember(int $storeId, int $userId, ?string $message, bool $sentRequiredMessage = true): void
    {
        $this->pickupGateway->deleteAllDatesFromAFoodsaver($userId, $storeId);
        $this->storeGateway->removeUserFromTeam($storeId, $userId);

        $storeLogAction = $this->session->id() == $userId ? StoreLogAction::LEFT_STORE : StoreLogAction::REMOVED_FROM_STORE;
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, $storeLogAction, $message);

        if ($teamChatConversationId = $this->storeGateway->getBetriebConversation($storeId)) {
            $this->messageGateway->deleteUserFromConversation($teamChatConversationId, $userId);
        }

        if ($jumperChatConversationId = $this->storeGateway->getBetriebConversation($storeId, true)) {
            $this->messageGateway->deleteUserFromConversation($jumperChatConversationId, $userId);
        }

        if ($sentRequiredMessage) {
            $this->messageTransactions->sendRequiredMessageToUser($userId, $this->session->id(), 'kick_from_store_team', $message, [
                '{storeId}' => $storeId,
                '{store}' => $this->storeGateway->getStoreName($storeId),
            ]);
        }
    }

    public function leaveAllStoreTeams(int $userId): void
    {
        $ownStoreIds = $this->storeGateway->listStoreIds($userId);

        foreach ($ownStoreIds as $storeId) {
            $this->removeStoreMember($storeId, $userId, null, false);
        }
    }

    public function moveMemberToStandbyTeam(int $storeId, int $userId, ?string $message, bool $sentRequiredMessage = true): void
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

        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::MOVED_TO_JUMPER, $message);

        if ($sentRequiredMessage) {
            $this->messageTransactions->sendRequiredMessageToUser($userId, $this->session->id(), 'move_to_standby_team', $message, [
                '{storeId}' => $storeId,
                '{store}' => $this->storeGateway->getStoreName($storeId),
            ]);
        }
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

    public function downgradeResponsibleMember(int $storeId, int $userId, ?string $message): void
    {
        /* check if other managers exist (cannot leave as last manager) */
        $this->storeGateway->removeStoreManager($storeId, $userId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), $userId, null, StoreLogAction::REMOVED_AS_STORE_MANAGER, $message);

        // Send a message to the user about the demotion
        $this->messageTransactions->sendRequiredMessageToUser($userId, $this->session->id(), 'demote_store_manager', $message, [
            '{storeId}' => $storeId,
            '{store}' => $this->storeGateway->getStoreName($storeId),
        ]);

        $standbyTeamChatId = $this->storeGateway->getBetriebConversation($storeId, true);
        if ($standbyTeamChatId) {
            $this->messageGateway->deleteUserFromConversation($standbyTeamChatId, $userId);
        }
    }

    private function addUserToStore(int $storeId, int $userId, bool $moveToStandby): void
    {
        $this->storeGateway->addUserToTeam($storeId, $userId);

        if ($moveToStandby) {
            $this->moveMemberToStandbyTeam($storeId, $userId, null, false);
        } else {
            $this->moveMemberToRegularTeam($storeId, $userId);
        }
    }

    // notify people who can do something with the request: store managers, region ambassadors, or orga
    private function notifyStoreManagersAboutRequest(int $storeId): void
    {
        $bellRecipients = $this->storeGateway->getBiebsForStore($storeId);
        if (!count($bellRecipients)) {
            $regionId = $this->storeGateway->getStoreRegionId($storeId);
            $bellRecipients = $this->foodsaverGateway->getAdminsOrAmbassadors($regionId);
        }
        if (!count($bellRecipients)) {
            $bellRecipients = $this->foodsaverGateway->getOrgaTeam();
        }
        $storeName = $this->storeGateway->getStoreName($storeId);

        $baseBell = Bell::create(
            'store_new_request_title',
            'new_store_request',
            'fas fa-user-plus',
            ['href' => '/store/' . $storeId . '?showTeamRequests'], [
                'user' => $this->session->user('name'),
                'name' => $storeName,
            ],
            BellType::createIdentifier(BellType::NEW_STORE_REQUEST, $storeId)
        );

        $this->bellTransactions->addGroupedBellEvent(array_column($bellRecipients, 'id'), $baseBell, $storeId);
    }

    private function triggerBellForJoining(int $storeId, int $userId, int $actionType): void
    {
        $bellLink = '/store/' . $storeId;
        if ($actionType === StoreLogAction::ADDED_WITHOUT_REQUEST) {
            $bellTitle = 'store_request_imposed_title';
            $bellMsg = 'store_request_imposed';
            $bellIcon = 'fas fa-user-plus';
            $bellId = BellType::createIdentifier(BellType::STORE_ADDED_WITHOUT_REQUEST, $storeId, $userId);
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
        } elseif ($actionType === StoreLogAction::INVITED_TO_TEAM) {
            $bellTitle = 'store_invite_title';
            $bellMsg = 'store_invite';
            $bellIcon = 'fas fa-shopping-cart';
            $bellId = BellType::createIdentifier(BellType::STORE_INVITATION, $storeId);
            $bellLink = "/karte?bid={$storeId}";
        } else {
            throw new \DomainException('Unknown store-team action: ' . $actionType);
        }

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
        $teamIds = array_column($this->storeGateway->getStoreTeam($storeId), 'id');
        $teamWithoutPostAuthor = array_diff($teamIds, [$this->session->id()]);

        $baseBell = Bell::create('store_cr_times_title', 'store_change_regular_pickup_times', 'fas fa-user-clock', [
            'href' => '/?page=fsbetrieb&id=' . $storeId,
        ], [
            'user' => $this->session->user('name'),
            'name' => $this->storeGateway->getStoreName($storeId),
        ], BellType::createIdentifier(BellType::STORE_TIME_CHANGED, $storeId));

        $this->bellTransactions->addGroupedBellEvent($teamWithoutPostAuthor, $baseBell, 0);
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
            $regionOptions = $this->regionGateway->getRegionOptions($regionId);
            if ($regionOptions->isRegionPickupRuleActive) {
                // how many hours before a pickup can this rule be ignored ?
                $res = (int)ceil(Carbon::now()->diffInHours($pickupDate, true));
                if ($res > $regionOptions->regionPickupRuleInactiveHours) {
                    // the allowed numbers of pickups in a timespan. Timespan is +/- from pickupdate
                    $numberAllowedPickups = $regionOptions->regionPickupRuleLimitNumber;
                    $intervall = $regionOptions->regionPickupRuleTimespanDays;
                    // if we have more or same amount of used slots occupied then allowed we return false
                    if ($this->pickupGateway->getNumberOfPickupsForUserWithStoreRules($fsId, $pickupDate->copy()->subDays($intervall), $pickupDate->copy()->addDays($intervall)) >= $numberAllowedPickups) {
                        return false;
                    }
                    // if we have more then or same amount of allowed pickups per day we return false
                    if ($this->pickupGateway->getNumberOfPickupsForUserWithStoreRulesSameDay($fsId, $pickupDate) >= $regionOptions->regionPickupRuleLimitDayNumber) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function deleteStore(int $storeId): void
    {
        //Add store log entry to add documentation at least in the database.
        $storeName = $this->storeGateway->getStoreName($storeId);
        $this->storeGateway->addStoreLog($storeId, $this->session->id(), null, null, StoreLogAction::DELETE_STORE, $storeName);
        $this->wallPostGateway->deletePostsForTarget(WallType::STORE, $storeId);

        //Send bell
        $team = $this->storeGateway->getStoreTeam($storeId, [
            MembershipStatus::JUMPER, MembershipStatus::MEMBER, MembershipStatus::APPLIED_FOR_TEAM
        ]);
        $teamIds = array_column($team, 'id');
        $storeName = $this->storeGateway->getStoreName($storeId);
        $bellData = Bell::create(
            'delete_store_title',
            'delete_store',
            'fas fa-shop-slash',
            ['href' => '/dashboard'],
            ['name' => $storeName],
            BellType::createIdentifier(BellType::DELETE_STORE, $storeId),
        );
        $this->bellGateway->addBell($teamIds, $bellData);

        // Delete the store before the chats due to foreign keys
        $this->storeGateway->deleteStore($storeId);

        //Clean store chats
        $this->messageGateway->deleteConversation($this->storeGateway->getBetriebConversation($storeId, false));
        $this->messageGateway->deleteConversation($this->storeGateway->getBetriebConversation($storeId, true));
    }

    /**
     * Returns all team member of the store (active and waiting list) and makes sure that details like the phone
     * number are only included if allowed.
     *
     * @param array $members the list of team members from the database
     * @param bool $includeUserDetails whether to include or omit phone numbers and last fetch date
     * @param bool $includeDistance whether to include or omit the distance information
     */
    private function getDisplayedStoreTeam(array $members, bool $includeUserDetails, bool $includeDistance): array
    {
        $allowedFields = [
            // personal info
            'id', 'name', 'firstName', 'photo', 'rolle', 'is_sleeping', 'verified', 'hygiene_certificate_until',
            // team-related info
            'verantwortlich', 'team_active', 'stat_fetchcount', 'add_date',
        ];
        if ($includeUserDetails) {
            array_push($allowedFields, 'handy', 'telefon', 'last_fetch');
        } else {
            foreach ($members as &$member) {
                if (isset($member['firstName'])) {
                    $member['name'] = $member['firstName'];
                }
            }
        }
        if ($includeDistance) {
            array_push($allowedFields, 'distance');
        }

        return array_map(
            fn ($teamMember) => array_filter($teamMember, fn ($key) => in_array($key, $allowedFields), ARRAY_FILTER_USE_KEY),
            $members
        );
    }
}
