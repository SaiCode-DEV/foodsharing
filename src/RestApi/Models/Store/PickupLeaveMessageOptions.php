<?php

namespace Foodsharing\RestApi\Models\Store;

use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Describes the message options for leaving a pickup slot.
 */
class PickupLeaveMessageOptions
{
    /**
     * Message why a user is removed from pickup.
     *
     * @OA\Property(example="We need a pick up slot for a new foodsaver")
     */
    #[Assert\Length(max: 3000)]
    public string $message = '';

    /**
     * Inform removed user about removin.
     *
     * @OA\Property(example=true)
     */
    public bool $sendKickMessage = true;
}
