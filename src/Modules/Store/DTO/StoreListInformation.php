<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;

class StoreListInformation
{
    /**
     * Unique identifier of the store in database.
     */
    public int $id;

    /**
     * Name of the store.
     */
    public ?string $name = null;

    /**
     * Region which is manages and is responsible for this store.
     */
    public ?MinimalRegionIdentifier $region = null;

    /**
     * Location of the store.
     */
    public ?GeoLocation $location = null;

    /**
     * Street name with number of store location.
     */
    public ?string $street = null;

    /** City name of store location */
    public ?string $city = null;

    /** Zip code of store location */
    public ?string $zipCode = null;

    /**
     * Cooperation status of store.
     *
     * @see CooperationStatus
     */
    public ?CooperationStatus $cooperationStatus = null;

    /**
     * Data of creation of store in database.
     *
     * The create date and the first cooperation can be different.
     *
     * The value is a date "Y-m-d"
     */
    public ?string $createdAt = null;

    /**
     * Constructor to create it from a Store instance.
     *
     * @param Store $store Store from database
     * @param bool $onlyId Fill only the Id into class
     */
    public static function loadFrom(Store $store, bool $onlyId): StoreListInformation
    {
        $obj = new StoreListInformation();
        $obj->id = $store->id;
        if (!$onlyId) {
            $obj->name = $store->name;
            $obj->cooperationStatus = $store->cooperationStatus;
            $obj->location = $store->location;
            $obj->region = new MinimalRegionIdentifier($store->region->id);
            $obj->street = $store->address->street;
            $obj->city = $store->address->city;
            $obj->zipCode = $store->address->postalCode;

            $obj->createdAt = $store->createdAt->format('Y-m-d');
        }

        return $obj;
    }
}
