<?php

namespace Foodsharing\Modules\Core\DTO;

/**
 * Represents an address, including street, postal code, and city.
 */
class Address
{
    public function __construct(
        /**
         * Street and street number.
         */
        public string $street = '',
        /**
         * Postal code.
         */
        public ?string $postalCode = null,
        /**
         * Name of the city.
         */
        public string $city = '',
    ) {
    }
}
