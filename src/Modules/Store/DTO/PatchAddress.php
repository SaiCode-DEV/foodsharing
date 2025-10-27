<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Validator\NoHtml;
use Symfony\Component\Validator\Constraints as Assert;

class PatchAddress
{
    /**
     * String with street and street number.
     */
    #[Assert\Length(max: 120)]
    #[NoHtml]
    public ?string $street = null;

    /**
     * String with city name.
     */
    #[Assert\Length(max: 50)]
    #[NoHtml]
    public ?string $city = null;

    /**
     * String with zip code of store.
     */
    #[Assert\Length(max: 5)]
    public ?string $postalCode = null;

    public static function apply(PatchAddress &$addressChange, Address &$storeAddress): bool
    {
        $patchNeeded = false;
        if (!empty($addressChange->street)) {
            $patchNeeded = true;
            $storeAddress->street = $addressChange->street;
        }
        if (!empty($addressChange->city)) {
            $patchNeeded = true;
            $storeAddress->city = $addressChange->city;
        }
        if (!empty($addressChange->postalCode)) {
            $patchNeeded = true;
            $storeAddress->postalCode = $addressChange->postalCode;
        }

        return $patchNeeded;
    }
}
