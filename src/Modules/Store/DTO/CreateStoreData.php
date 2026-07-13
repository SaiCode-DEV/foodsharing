<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Region\DTO\MinimalRegionIdentifier;
use Foodsharing\Validator\MarkdownOrPlainText;
use Symfony\Component\Validator\Constraints as Assert;

class CreateStoreData
{
    /**
     * Name of the store.
     */
    public string $name;

    /**
     * Region which is manages and is responsible for this store.
     */
    public int $regionId;

    /**
     * Location of the store.
     */
    public GeoLocation $location;

    /**
     * Street name with street number.
     */
    public string $street;

    /**
     * Zip code.
     */
    public string $zipCode;

    /**
     * City name.
     */
    public string $city;

    /**
     * Public information about the store which is visible
     * for users which are looking for a store.
     */
    #[Assert\Length(max: 200)]
    #[MarkdownOrPlainText]
    public string $publicInfo;

    public function __construct(GeoLocation $location)
    {
        $this->location = $location;
    }

    public function toStore(): Store
    {
        $store = new Store($this->location);
        $store->name = $this->name;
        $store->region = new MinimalRegionIdentifier($this->regionId);
        $store->address->street = $this->street;
        $store->address->postalCode = $this->zipCode;
        $store->address->city = $this->city;
        $store->publicInfo = $this->publicInfo;

        return $store;
    }
}
