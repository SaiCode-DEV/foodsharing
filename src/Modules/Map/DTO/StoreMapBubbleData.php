<?php

namespace Foodsharing\Modules\Map\DTO;

use DateTime;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;

class StoreMapBubbleData
{
    /**
     * Id of the store.
     */
    public int $id = 0;

    /**
     * Name of the store.
     */
    public string $name = '';

    /**
     * Name of the region the store belongs to.
     */
    public string $regionName = '';

    /**
     * The number of foodsavers in the store's team without the standby list.
     */
    public int $teamMemberCount = 0;

    /**
     * The number of foodsavers in the store's standby list.
     */
    public int $standbyCount = 0;

    /**
     * The number of pickup dates assigned to the store since its creation.
     */
    public int $pickupCount = 0;

    /**
     * The estimated total weight of food that was picked up at the store in total.
     */
    public float $pickupWeightInKg = 0;

    /**
     * All managers of the store.
     *
     * @var Profile[]
     */
    public array $managers = [];

    /**
     * The date at which the cooperation with the store started. Can be null if the store is not cooperating yet.
     */
    public ?DateTime $cooperationStart = null;

    /**
     * Any public information text that will be visible in the store's bubble on the map as well as on the store page.
     */
    public ?string $publicInformation = null;

    /**
     * Approximate time of day at which pickups at this store usually are.
     */
    public PublicTimes $publicPickupTime = PublicTimes::NOT_SET;

    /**
     * The team search indicates whether the store's team is looking for new members.
     */
    public TeamSearchStatus $teamSearchStatus = TeamSearchStatus::CLOSED;

    /**
     * The cooperation status indicates whether the store is currently cooperating with Foodsharing.
     */
    public CooperationStatus $cooperationStatus = CooperationStatus::UNCLEAR;

    /**
     * Whether the user is allowed to access the store's page.
     */
    public bool $mayAccessStorePage = false;

    /**
     * Whether the user is allowed to send a request for joining the store team.
     */
    public bool $maySendRequest = false;

    /**
     * Whether the user is allowed to accept an invitation for joining the store team.
     */
    public bool $mayAcceptInvitation = false;

    /**
     * Whether the user has sent a request for joining the store team and is allowed to withdraw it.
     */
    public bool $mayWithdrawRequest = false;

    /**
     * Position of the store.
     */
    public ?GeoLocation $location = null;

    /**
     * Whether users are required to have a hygiene certificate to apply to the store.
     */
    public bool $isHygieneRequired = false;

    /**
     * Whether the user currently has a valid hygiene certificate. Only set if a hygiene certificate is required.
     */
    public ?bool $hasHygieneCertificate = null;

    /**
     * Whether user has a complete profile.
     */
    public bool $hasCompleteProfile = false;

    /**
     * Whether user has a home region.
     */
    public bool $hasHomeRegion = false;

    /**
     * Whether user is member of the store's region.
     */
    public bool $isMemberOfRegion = false;

    /**
     * Whether users are required to be verified to apply to the store.
     */
    public bool $requireVerification = false;

    /**
     * Whether users are required to have a valid phone number to apply to the store.
     */
    public bool $requirePhone = false;

    /**
     * Whether users are required to have a text in their application to apply to the store.
     */
    public bool $requireApplyText = false;

    /**
     * Whether the user is invited to the store.
     */
    public bool $isInvited = false;

    /**
     * The ID of the region this store belongs to.
     */
    public int $regionId = 0;

    /**
     * The type of the store category.
     */
    public StoreCategoryType $categoryType = StoreCategoryType::PICKUP;
}
