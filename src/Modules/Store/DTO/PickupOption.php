<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

/**
 * Describes a pickup the foodsaver could or did take.
 */
class PickupOption
{
    /**
     * Date time of the pickup.
     */
    public DateTime $date;

    /**
     * Store in which the pickup will happen.
     */
    public MinimalStoreIdentifier $store;

    /**
     * Whether the users pickup slot is confirmed. null if the user currently has no pickup slot.
     */
    public ?bool $isConfirmed = null;

    /**
     * Total number of slots in the pickup.
     * null for past pickups.
     */
    public ?int $slots = null;

    /**
     * Description of the pickup. null if the pickup has no additional text.
     */
    public ?string $description = null;

    /**
     * Profiles of the users that already occupy a slot.
     * @var Profile[]
     */
    public array $occupiedSlots = [];
}
