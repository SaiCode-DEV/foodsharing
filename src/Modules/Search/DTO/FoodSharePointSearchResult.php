<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Attributes as OA;

class FoodSharePointSearchResult extends SearchResult
{
    #[OA\Property(example: 'Oskar-Michels-Ring 29')]
    public string $street;

    #[OA\Property(example: '12345')]
    public string $zipCode;

    #[OA\Property(example: 'Münster', description: 'City of the food share points adress.')]
    public string $city;

    #[OA\Property(example: 1)]
    public int $regionId;

    #[OA\Property(example: 'Münster', description: 'Name of the food share points region.')]
    public string $regionName;

    public static function createFromArray(array $data): FoodSharePointSearchResult
    {
        $result = new FoodSharePointSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->street = $data['street'];
        $result->zipCode = $data['zip'];
        $result->city = $data['city'];
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->setSearchString($data);

        return $result;
    }
}
