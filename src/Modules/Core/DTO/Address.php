<?php

namespace Foodsharing\Modules\Core\DTO;

/**
 * Represents an address, including street, postal code, and city.
 */
class Address
{
    /**
     * Street and street number.
     */
    public string $street = '';

    /**
     * zip code.
     */
    public string $postalCode = '';

    /**
     * Name of city.
     */
    public string $city = '';

    /**
     * Generates from an array like a DB result.
     */
    public static function createFromArray(array $query_result): Address
    {
        $obj = new Address();
        $obj->street = $query_result['street'];
        $obj->postalCode = $query_result['postalCode'];
        $obj->city = $query_result['city'];

        return $obj;
    }
}
