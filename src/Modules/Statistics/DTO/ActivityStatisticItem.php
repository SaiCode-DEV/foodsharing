<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Statistics\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'An element of statistical information for the ranking list')]
class ActivityStatisticItem
{
    #[OA\Property(description: 'The Name', type: 'string')]
    #[Type('string')]
    public string $name;

    #[OA\Property(description: 'Food successfully saved from the garbage can.', type: 'number', format: 'float')]
    #[Type('float')]
    public float $fetchWeight;

    #[OA\Property(description: 'Number of rescue missions', type: 'integer')]
    #[Type('integer')]
    public int $fetchCount;

    public static function create(string $name, float $fetchWeight, int $fetchCount): ActivityStatisticItem
    {
        $item = new ActivityStatisticItem();
        $item->name = $name;
        $item->fetchWeight = $fetchWeight;
        $item->fetchCount = $fetchCount;

        return $item;
    }
}
