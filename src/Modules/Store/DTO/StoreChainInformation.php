<?php

namespace Foodsharing\Modules\Store\DTO;

class StoreChainInformation
{
    /**
     * Identifier of the store chain.
     */
    public int $id;

    /**
     * Public information about the chain.
     */
    public ?string $information;

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
