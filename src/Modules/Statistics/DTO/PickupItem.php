<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Statistics\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'basic statistics data for pickup')]
class PickupItem
{
    #[OA\Property(description: 'Name of the pickup', type: 'string')]
    #[Type('string')]
    public string $name;

    #[OA\Property(description: 'fetch count from pickup', type: 'integer')]
    #[Type('integer')]
    public int $fetchCount;

    public static function create(string $name, int $fetchCount): PickupItem
    {
        $item = new PickupItem();
        $item->name = $name;
        $item->fetchCount = $fetchCount;

        return $item;
    }
}
