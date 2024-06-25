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

    public static function createFromArray(array $data): Address
    {
        $result = new Address();
        $result->street = $data['street'];
        $result->city = $data['city'];
        $result->zipCode = $data['zip'];

        return $result;
    }
}
