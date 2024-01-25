<?php

namespace Foodsharing\Modules\Store\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class Address
{
    /**
     * String with street and street number.
     */
    #[Assert\Length(max: 120)]
    public ?string $street = null;

    /**
     * String with city name.
     */
    #[Assert\Length(max: 50)]
    public ?string $city = null;

    /**
     * String with zip code of store.
     */
    #[Assert\Length(max: 5)]
    public ?string $zipCode = null;
}
