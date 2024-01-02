<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use OpenApi\Annotations as OA;

class StoreSearchResult extends SearchResult
{
    /**
     * Cooperation status of the store.
     *
     * @OA\Property(example=5)
     */
    public CooperationStatus $cooperation_status;

    /**
     * Street in which the store lays.
     *
     * @OA\Property(example="Oskar-Michels-Ring 29")
     */
    public string $street;

    /**
     * Zip code of the stores adress.
     *
     * @OA\Property(example="Oskar-Michels-Ring 29")
     */
    public string $zipCode;

    /**
     * City of the stores adress.
     *
     * @OA\Property(example="Münster")
     */
    public string $city;

    /**
     * Unique identifier of the stores region.
     *
     * @OA\Property(example=1)
     */
    public int $region_id;

    /**
     * Name of the stores region.
     *
     * @OA\Property(example="Münster")
     */
    public string $region_name;

    /**
     * Identifier of the searching users membership to the store.
     *
     * @OA\Property(example=1)
     */
    public ?int $membership_status;

    /**
     * Whether the searching user is manager of the store.
     *
     * @OA\Property(example=true)
     */
    public bool $is_manager;

    /**
     * Name of the chain the store belongs to, null if the store has no chain.
     *
     * @OA\Property(example=null)
     */
    public ?string $chain_name;

    public static function createFromArray(array $data): StoreSearchResult
    {
        $result = new StoreSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->cooperation_status = CooperationStatus::from($data['cooperation_status']);
        $result->street = $data['street'];
        $result->zipCode = $data['zip'];
        $result->city = $data['city'];
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->membership_status = $data['membership_status'];
        $result->is_manager = boolval($data['is_manager']);
        $result->chain_name = $data['chain_name'];
        $result->setSearchString($data);

        return $result;
    }
}
