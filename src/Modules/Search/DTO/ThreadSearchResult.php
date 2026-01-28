<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Carbon\Carbon;
use DateTime;
use OpenApi\Attributes as OA;

class ThreadSearchResult extends SearchResult
{
    public DateTime $lastPostSentAt;

    #[OA\Property(example: 1, description: 'Whether the thread is pinned. Higher values for higher priority.')]
    public int $pinnedLevel;

    public bool $isClosed;

    #[OA\Property(description: 'Whether the thread is located in the ambassador forum.')]
    public bool $isInsideAmbassadorForum;

    #[OA\Property(description: 'Unique identifier of the forums region.')]
    public int $regionId;

    #[OA\Property(example: 'Münster', description: 'Name of the forums region.')]
    public string $regionName;

    #[OA\Property(example: 'This is the body of the thread', description: 'The body of the forum thread. Only included when searching by post content.')]
    public ?string $body;

    #[OA\Property(example: 0.85, description: 'The relevance score of the thread.')]
    public float $relevance;

    public static function createFromArray(array $data): ThreadSearchResult
    {
        $result = new ThreadSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->lastPostSentAt = Carbon::parse($data['time']);
        $result->pinnedLevel = $data['stickiness'];
        $result->isClosed = boolval($data['is_closed']);
        $result->isInsideAmbassadorForum = boolval($data['is_inside_ambassador_forum']);
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->body = $data['body'] ?? null;
        $result->setSearchString($data);
        $result->relevance = floatval($data['relevance'] ?? -1.0);

        return $result;
    }
}
