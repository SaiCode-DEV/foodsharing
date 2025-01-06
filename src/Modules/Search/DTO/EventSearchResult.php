<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Attributes as OA;

class EventSearchResult extends SearchResult
{
    #[OA\Property(description: 'The name of the events location.', example: 'foodsharing Developer')]
    public ?string $location_name = null;

    #[OA\Property(description: 'The address details of the location.')]
    public array $location;

    #[OA\Property(description: 'The users invitation status', example: '1')]
    public ?int $status = null;

    #[OA\Property(description: 'When the event starts', example: '2023-10-04 15:21:52')]
    public string $start;

    #[OA\Property(description: 'When the event starts', example: '2023-10-04 15:21:52')]
    public string $end;

    #[OA\Property(description: 'The event location type', example: 1)]
    public int $location_type;

    #[OA\Property(description: 'The id of the hosting region / group', example: 1)]
    public int $region_id;

    #[OA\Property(description: 'The name of the hosting region / group', example: 'Münster')]
    public string $region_name;

    public static function createFromArray(array $data): EventSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->start = $data['start'];
        $result->end = $data['end'];
        $result->location_type = $data['online'];
        $result->status = $data['status'];
        $result->location_name = $data['location_name'];
        $result->location = [$data['street'], $data['zip'], $data['city']];
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->setSearchString($data);

        return $result;
    }
}
