<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Annotations as OA;

class ThreadSearchResult extends SearchResult
{
    /**
     * The time at which the last post was send in the thread.
     *
     * @OA\Property(example="2023-10-04 15:21:52")
     */
    public string $time;

    /**
     * Whether the thread is sticky / pinned.
     *
     * @OA\Property(example=1)
     */
    public int $stickiness;

    /**
     * Whether the thread is closed.
     *
     * @OA\Property(example=false)
     */
    public bool $is_closed;

    /**
     * Whether the thread is located in the ambassador forum.
     *
     * @OA\Property(example=false)
     */
    public bool $is_inside_ambassador_forum;

    /**
     * Unique identifier of the forums region.
     *
     * @OA\Property(example=1)
     */
    public int $region_id;

    /**
     * Name of the forums region.
     *
     * @OA\Property(example="Münster")
     */
    public string $region_name;

    /**
     * The body of the forum thread. Only included when searching by post content.
     *
     * @OA\Property(example="This is the body of the thread.")
     */
    public ?string $body;

    /**
     * The relevance score of the thread.
     *
     * @OA\Property(example=0.85)
     */
    public float $relevance;

    public static function createFromArray(array $data): ThreadSearchResult
    {
        $result = new ThreadSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->time = $data['time'];
        $result->stickiness = $data['stickiness'];
        $result->is_closed = boolval($data['is_closed']);
        $result->is_inside_ambassador_forum = boolval($data['is_inside_ambassador_forum']);
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->body = $data['body'] ?? null;
        $result->setSearchString($data);
        $result->relevance = floatval($data['relevance'] ?? -1.0);

        return $result;
    }
}
