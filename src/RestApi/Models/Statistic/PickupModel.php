<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Statistic;

use Foodsharing\Modules\Statistics\DTO\ActivityStatisticItem;
use Foodsharing\Modules\Statistics\DTO\PickupItem;
use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Statistics in three different time periods')]
class PickupModel
{
    #[OA\Property(
        description: 'pick-ups over all time',
        type: 'array',
        items: new OA\Items(ref: new Model(type: ActivityStatisticItem::class))
    )]
    #[Type('array')]
    public readonly array $pickupOverAllTime;

    #[OA\Property(
        description: 'pick-ups in the defined period',
        type: 'array',
        items: new OA\Items(ref: new Model(type: PickupItem::class)),
        nullable: true
    )]
    #[Type('array')]
    public readonly array $pickupInDefinedPeriod;

    #[OA\Property(
        description: 'pick-ups in current month',
        type: 'array',
        items: new OA\Items(ref: new Model(type: PickupItem::class)),
        nullable: true
    )]
    #[Type('array')]
    public readonly array $pickupCurrentMonth;

    /**
     * @param array<ActivityStatisticItem> $pickupOverAllTime
     * @param array<PickupItem>|null       $pickupInDefinedPeriod
     * @param array<PickupItem>|null       $pickupCurrentMonth
     */
    public function __construct(
        array $pickupOverAllTime,
        ?array $pickupInDefinedPeriod = null,
        ?array $pickupCurrentMonth = null
    ) {
        $this->pickupOverAllTime = $pickupOverAllTime;
        $this->pickupInDefinedPeriod = $pickupInDefinedPeriod ?? [];
        $this->pickupCurrentMonth = $pickupCurrentMonth ?? [];
    }
}
