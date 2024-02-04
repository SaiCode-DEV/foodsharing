<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Statistics\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

class TotalStatisticItem
{
    #[OA\Property(description: 'Food successfully saved from the garbage can.', type: 'number', format: 'float')]
    #[Type('float')]
    public float $fetchWeight;

    #[OA\Property(description: 'Number of rescue missions', type: 'integer')]
    #[Type('integer')]
    public int $fetchCount;

    #[OA\Property(description: 'Number of cooperating companies', type: 'integer')]
    #[Type('integer')]
    public int $cooperationsCount;

    #[OA\Property(description: 'Number of ambassadors', type: 'integer')]
    #[Type('integer')]
    public int $botCount;

    #[OA\Property(description: 'Number of Foodsaver volunteers', type: 'integer')]
    #[Type('integer')]
    public int $foodsaverCount;

    #[OA\Property(description: 'Number of fairteiler used', type: 'integer')]
    #[Type('integer')]
    public int $fairteilerCount;

    public static function create(
        float $fetchWeight,
        int $fetchCount,
        int $cooperationsCount,
        int $botCount,
        int $foodsaverCount,
        int $fairteilerCount
    ) {
        $item = new TotalStatisticItem();
        $item->fetchWeight = $fetchWeight;
        $item->fetchCount = $fetchCount;
        $item->cooperationsCount = $cooperationsCount;
        $item->botCount = $botCount;
        $item->foodsaverCount = $foodsaverCount;
        $item->fairteilerCount = $fairteilerCount;

        return $item;
    }
}
