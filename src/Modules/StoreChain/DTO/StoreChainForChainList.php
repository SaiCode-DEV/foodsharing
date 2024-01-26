<?php

namespace Foodsharing\Modules\StoreChain\DTO;

use OpenApi\Annotations as OA;

/**
 * Class that represents the data of a store chain, in a format in which it is sent to the client.
 * This is not an entity class, it does not provide any domain logic nor does it contain any access
 * logic. You can see it more like a Data Transfer Object (DTO) used to pass a chains data between
 * parts of the application in a unified format.
 */
class StoreChainForChainList
{
    public StoreChain $chain;

    /**
     * The number of stores that are part of this chain.
     *
     * @OA\Property(example=5)
     */
    public ?int $storeCount = null;

    public static function createFromArray(array $data): StoreChainForChainList
    {
        $obj = new StoreChainForChainList();
        $obj->chain = StoreChain::createFromArray($data);
        $obj->storeCount = $data['stores'];

        return $obj;
    }
}
