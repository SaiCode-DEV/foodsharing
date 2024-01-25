<?php

namespace Foodsharing\Modules\Store\DTO;

/**
 * Describes a one time pickup at store.
 */
class PickupInformation
{
    /**
     * information about pickup.
     */
    public OneTimePickup $information;

    /**
     * Ids of pickup members with sign up (confirmed and no confirmed).
     *
     * @var PickupSignUp[] Users which have tried to confirm
     */
    public array $signUps = [];

    public function __construct(OneTimePickup $information)
    {
        $this->information = $information;
    }

    public function hasConfirmedUser()
    {
        $hasConfirmedPickup = false;
        if ($this->information->slots != 0) {
            $confirmedPickups = array_filter(
                $this->signUps,
                function (PickupSignUp $signUps) {
                    return $signUps->isConfirmed;
                }
            );

            $hasConfirmedPickup = count($confirmedPickups) != 0;
        } else {
            $hasConfirmedPickup = true;
        }

        return $hasConfirmedPickup;
    }
}
