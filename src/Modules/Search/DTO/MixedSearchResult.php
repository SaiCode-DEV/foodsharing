<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class MixedSearchResult
{
    /**
     * Array of regions.
     *
     * @var array<RegionSearchResult> Array of regions
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=RegionSearchResult::class))
     * )
     */
    public array $regions;

    /**
     * Array of workingGroups.
     *
     * @var array<WorkingGroupSearchResult> Array of workingGroups
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=WorkingGroupSearchResult::class))
     * )
     */
    public array $workingGroups;

    /**
     * Array of stores.
     *
     * @var array<StoreSearchResult> Array of stores
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=StoreSearchResult::class))
     * )
     */
    public array $stores;

    /**
     * Array of foodSharePoints.
     *
     * @var array<FoodSharePointSearchResult> Array of foodSharePoints
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=FoodSharePointSearchResult::class))
     * )
     */
    public array $foodSharePoints;

    /**
     * Array of chats.
     *
     * @var array<ChatSearchResult> Array of chats
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=ChatSearchResult::class))
     * )
     */
    public array $chats;

    /**
     * Array of threads.
     *
     * @var array<ThreadSearchResult> Array of threads
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=ThreadSearchResult::class))
     * )
     */
    public array $threads;

    /**
     * Array of users.
     *
     * @var array<UserSearchResult> Array of users
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=UserSearchResult::class))
     * )
     */
    public array $users;

    public array $timings; // Only used for testing in beta.
}
