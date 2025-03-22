<?php

namespace Foodsharing\Modules\Store\DTO;

use Foodsharing\Modules\Store\StoreTransactions;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

/**
 * Describes the common store metadata.
 */
class CommonStoreMetadata
{
    /**
     * Maximum count of slots per pickup.
     *
     * The count of slots are limited by the foodsharing platform.
     *
     * @OA\Property(format="int64")
     */
    public int $maxCountPickupSlot = StoreTransactions::MAX_SLOTS_PER_PICKUP;

    /**
     * List of possible groceries.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $groceries = [];

    /**
     * List of existing store chains.
     *
     * Only provided with the permission to create stores.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)), nullable=true)
     */
    public ?array $storeChains = null;

    /**
     * List of store categories.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $categories = [];

    /**
     * List of available store status.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $status = [];

    /**
     * List of weight.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $weight = [];

    /**
     * List of convince status.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $convinceStatus = [];

    /**
     * List of typical pickup ranges.
     *
     * @OA\Property(type="array", @OA\Items(ref=@Model(type=CommonLabel::class)))
     */
    public array $publicTimes = [];

    /**
     * The version of the cache.
     * When the data is changed, the version number is increased.
     * Used to decide when data needs to be refetched.
     *
     * Should be removed when we switch to HTTP caching by Symfony.
     */
    public int $version;
}
