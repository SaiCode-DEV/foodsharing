<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Attributes as OA;

class PollSearchResult extends SearchResult
{
    #[OA\Property(description: 'When the poll starts', example: '2023-10-04 15:21:52')]
    public string $start;

    #[OA\Property(description: 'When the poll ends', example: '2023-10-04 15:21:52')]
    public string $end;

    #[OA\Property(description: 'The id of the hosting region / group', example: 1)]
    public int $region_id;

    #[OA\Property(description: 'The name of the hosting region / group', example: 'Münster')]
    public string $region_name;

    #[OA\Property(description: 'Whether the user has already voted', example: true)]
    public ?bool $has_voted;

    public static function createFromArray(array $data): PollSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->start = $data['start'];
        $result->end = $data['end'];
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->has_voted = is_null($data['has_voted']) ? null : boolval($data['has_voted']);
        $result->setSearchString($data);

        return $result;
    }
}
