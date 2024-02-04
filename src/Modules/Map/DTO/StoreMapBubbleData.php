<?php

namespace Foodsharing\Modules\Map\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
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
     * The date at which the cooperation with the store started.
     */
    public DateTime $cooperationStart;

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
     * Whether the user has sent a request for joining the store team and is allowed to withdraw it.
     */
    public bool $mayWithdrawRequest = false;
}
