<?php

namespace Foodsharing\RestApi\Models\Store;

use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Store\DTO\StoreStatusForMember;
use OpenApi\Annotations as OA;

/**
 * Desribes the essential store information for a foodsaver.
 *
 * @OA\Schema(required={"id", "name", "isManaging", "membershipStatus"})
 */
class StoreStatusForMemberModel
{
    /**
     * The unique identifier of the store.
     *
     * @OA\Property(format="int64", example=1)
     */
    public int $id;

    /**
     * The name of the store.
     *
     * @OA\Property(type="string", example="Govinda Natur GmbH")
     */
    public string $name;

    /**
     * The member is a store manager for this store.
     *
     * @OA\Property(type="boolean", example="false")
     */
    public bool $isManaging;

    /**
     * Indicates the kind of membership for foodsaver is for this store.
     *
     * - '0' - A store team request open, but not applied.
     * - '1' - In regular member of the store team.
     * - '2' - Is a inactive/standby/jumper member of the store team
     *        (This members have restricted access to store ressources and can interact only with store manager)
     *
     * @OA\Property(type="int",enum={"0", "1", "2"})
     */
    public int $membershipStatus;

    /**
     * Indicator about the next open pickup for the store.
     * Is only avialable for regular members.
     *
     * - '0' - Pick up with free slot is in future
     * - '1' - Next pick up with free slots is in 5 days
     * - '2' - Next pick up with free slots is 1-4 days
     * - '3' - Next pick up with free slots is today days
     * - null - No provided pick up information for this membership status
     *
     * @OA\Property(type="int",enum={"0", "1", "2", "3", null})
     */
    public ?int $pickupStatus = null;

    /**
     * Category Type of the store.
     */
    public StoreCategoryType $categoryType = StoreCategoryType::PICKUP;

    /**
     * @param StoreStatusForMember $model Model to recreate from
     */
    public function __construct(StoreStatusForMember $model)
    {
        $this->id = $model->store->id;
        $this->name = $model->store->name;
        $this->isManaging = $model->isManaging;
        $this->membershipStatus = $model->membershipStatus;
        if (isset($model->categoryType)) {
            $this->categoryType = $model->categoryType;
        }
        if (isset($model->pickupStatus)) {
            $this->pickupStatus = $model->pickupStatus;
        }
    }
}
