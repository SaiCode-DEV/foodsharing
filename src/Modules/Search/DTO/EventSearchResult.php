<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Carbon\Carbon;
use DateTime;
use OpenApi\Attributes as OA;

class EventSearchResult extends SearchResult
{
    #[OA\Property(description: 'The name of the events location.', example: 'foodsharing Developer')]
    public ?string $locationName = null;

    #[OA\Property(description: 'The address details of the location.')]
    public array $location;

    #[OA\Property(description: 'The users invitation status', example: '1')]
    public ?int $status = null;

    #[OA\Property(description: 'When the event starts')]
    public DateTime $startAt;

    #[OA\Property(description: 'When the event starts')]
    public DateTime $endAt;

    #[OA\Property(description: 'The event location type', example: 1)]
    public int $locationType;

    #[OA\Property(description: 'The id of the hosting region / group', example: 1)]
    public int $regionId;

    #[OA\Property(description: 'The name of the hosting region / group', example: 'Münster')]
    public string $regionName;

    public static function createFromArray(array $data): EventSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->startAt = Carbon::parse($data['start']);
        $result->endAt = Carbon::parse($data['end']);
        $result->locationType = $data['online'];
        $result->status = $data['status'];
        $result->locationName = $data['location_name'];
        $result->location = [$data['street'], $data['zip'], $data['city']];
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->setSearchString($data);

        return $result;
    }
}
