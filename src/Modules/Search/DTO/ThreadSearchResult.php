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
     * @OA\Property(example=true)
     */
    public bool $is_sticky;

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

    public static function createFromArray(array $data): ThreadSearchResult
    {
        $result = new ThreadSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->time = $data['time'];
        $result->is_sticky = boolval($data['is_sticky']);
        $result->is_closed = boolval($data['is_closed']);
        $result->is_inside_ambassador_forum = boolval($data['is_inside_ambassador_forum']);
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->setSearchString($data);

        return $result;
    }
}
