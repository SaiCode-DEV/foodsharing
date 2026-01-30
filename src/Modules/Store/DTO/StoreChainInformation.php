<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class StoreChainInformation
{
    /**
     * Identifier of the store chain.
     */
    public int $id;

    public ?string $name = null;

    /**
     * Public information about the chain.
     */
    public ?string $information = null;

    /**
     * @var Profile[] Kams of the chain
     */
    public array $kams = [];

    public static function createFromId(?int $id): ?StoreChainInformation
    {
        if ($id) {
            $entity = new StoreChainInformation();
            $entity->id = $id;

            return $entity;
        }

        return null;
    }
}
