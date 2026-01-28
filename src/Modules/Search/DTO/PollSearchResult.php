<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Carbon\Carbon;
use DateTime;
use OpenApi\Attributes as OA;

class PollSearchResult extends SearchResult
{
    #[OA\Property(description: 'When the poll starts', example: '2023-10-04 15:21:52')]
    public DateTime $startAt;

    #[OA\Property(description: 'When the poll ends', example: '2023-10-04 15:21:52')]
    public DateTime $endAt;

    #[OA\Property(description: 'The id of the hosting region / group', example: 1)]
    public int $regionId;

    #[OA\Property(description: 'The name of the hosting region / group', example: 'Münster')]
    public string $regionName;

    #[OA\Property(description: 'Whether the user has already voted', example: true)]
    public ?bool $hasVoted = null;

    public static function createFromArray(array $data): PollSearchResult
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->startAt = Carbon::parse($data['start']);
        $result->endAt = Carbon::parse($data['end']);
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->hasVoted = is_null($data['has_voted']) ? null : boolval($data['has_voted']);
        $result->setSearchString($data);

        return $result;
    }
}
