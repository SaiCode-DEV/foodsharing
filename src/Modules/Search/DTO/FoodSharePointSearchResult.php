<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Annotations as OA;

class FoodSharePointSearchResult extends SearchResult
{
    /**
     * Street in which the food share point lays.
     *
     * @OA\Property(example="Oskar-Michels-Ring 29")
     */
    public string $street;

    /**
     * Zip code of the food share points adress.
     *
     * @OA\Property(example="12345")
     */
    public string $zipCode;

    /**
     * City of the food share points adress.
     *
     * @OA\Property(example="Münster")
     */
    public string $city;

    /**
     * Unique identifier of the food share points region.
     *
     * @OA\Property(example=1)
     */
    public int $region_id;

    /**
     * Name of the food share points region.
     *
     * @OA\Property(example="Münster")
     */
    public string $region_name;

    public static function createFromArray(array $data): FoodSharePointSearchResult
    {
        $result = new FoodSharePointSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->street = $data['street'];
        $result->zipCode = $data['zip'];
        $result->city = $data['city'];
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->setSearchString($data);

        return $result;
    }
}
