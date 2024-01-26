<?php

namespace Foodsharing\RestApi\Models\Store;

use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Store\DTO\CreateStoreData;
use Foodsharing\Validator\NoHtml;
use Symfony\Component\Validator\Constraints as Assert;

class CreateStoreInformationModel
{
    /**
     * Name of the store.
     *
     * @NoHtml
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    /**
     * Location of the store.
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?GeoLocation $location = null;

    /**
     * Street name with street number.
     *
     * @NoHtml
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 120)]
    public ?string $street = null;

    /**
     * Zip code.
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 5)]
    public ?string $zipCode = null;

    /**
     * City name.
     *
     * @NoHtml
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 50)]
    public ?string $city = null;

    /**
     * Public information about the store which is visible
     * for users which are looking for a store.
     *
     * @NoHtml
     */
    #[Assert\NotNull]
    #[Assert\Length(max: 200)]
    public ?string $publicInfo = null;

    public function toCreateStore(): CreateStoreData
    {
        $store = new CreateStoreData();
        $store->name = $this->name;
        $store->location = $this->location;
        $store->street = $this->street;
        $store->zipCode = $this->zipCode;
        $store->city = $this->city;
        $store->publicInfo = $this->publicInfo;

        return $store;
    }
}
