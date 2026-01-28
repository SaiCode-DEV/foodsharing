<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use OpenApi\Attributes as OA;

class StoreSearchResult extends SearchResult
{
    #[OA\Property(example: 5, description: 'Cooperation status of the store')]
    public CooperationStatus $cooperationStatus;

    #[OA\Property(example: 'Oskar-Michels-Ring 29', description: 'Street in which the store lays')]
    public string $street;

    #[OA\Property(example: '48163', description: 'Zip code of the stores adress')]
    public string $zipCode;

    #[OA\Property(example: 'Münster', description: 'City of the stores adress')]
    public string $city;

    #[OA\Property(example: 1)]
    public int $regionId;

    #[OA\Property(example: 'Münster')]
    public string $regionName;

    #[OA\Property(example: 1, description: 'Identifier of the searching users membership to the store')]
    public ?int $membershipStatus = null;

    #[OA\Property(description: 'Whether the searching user is manager of the store')]
    public bool $isManager;

    #[OA\Property(example: null, description: 'Name of the chain the store belongs to, null if the store has no chain')]
    public ?string $chainName = null;

    public static function createFromArray(array $data): StoreSearchResult
    {
        $result = new StoreSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->cooperationStatus = CooperationStatus::from($data['cooperation_status']);
        $result->street = $data['street'];
        $result->zipCode = $data['zip'];
        $result->city = $data['city'];
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->membershipStatus = $data['membership_status'];
        $result->isManager = boolval($data['is_manager']);
        $result->chainName = $data['chain_name'];
        $result->setSearchString($data);

        return $result;
    }
}
