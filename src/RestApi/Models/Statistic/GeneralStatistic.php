<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Statistic;

use Foodsharing\Modules\Statistics\DTO\TotalStatisticItem;
use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Represents the overall statistics')]
class GeneralStatistic
{
    #[OA\Property(description: 'Food successfully saved from the garbage can.', type: 'number', format: 'float')]
    #[Type('float')]
    public readonly float $fetchWeight;

    #[OA\Property(description: 'Number of rescue missions', type: 'integer')]
    #[Type('integer')]
    public readonly int $fetchCount;

    #[OA\Property(description: 'Number of cooperating companies', type: 'integer')]
    #[Type('integer')]
    public readonly int $cooperationsCount;

    #[OA\Property(description: 'Number of ambassadors', type: 'integer')]
    public readonly int $botCount;

    #[OA\Property(description: 'Number of Foodsaver volunteers', type: 'integer')]
    #[Type('integer')]
    public readonly int $foodsaverCount;

    #[OA\Property(description: 'Number of fairteiler used', type: 'integer')]
    #[Type('integer')]
    public readonly int $fairteilerCount;

    #[OA\Property(description: 'Total number of food baskets', type: 'integer')]
    #[Type('integer')]
    public readonly int $totalBaskets;

    #[OA\Property(description: 'Average food baskets per week', type: 'integer')]
    #[Type('integer')]
    public readonly int $avgWeeklyBaskets;

    #[OA\Property(description: 'Number of registered Foodsaver', type: 'integer')]
    #[Type('integer')]
    public readonly int $countAllFoodsaver;

    #[OA\Property(description: 'Number of fairteiler used for food exchange', type: 'integer')]
    #[Type('integer')]
    public readonly int $countActiveFoodSharePoints;

    #[OA\Property(description: 'Average rescue missions per day', type: 'integer')]
    #[Type('integer')]
    public readonly int $avgDailyFetchCount;

    public function __construct(
        TotalStatisticItem $totalStatsItem,
        int $totalBaskets,
        int $avgWeeklyBaskets,
        int $countAllFoosharer,
        int $countActiveFoodSharePoints,
        int $avgDailyFetchCount
    ) {
        $this->fetchWeight = $totalStatsItem->fetchWeight;
        $this->fetchCount = $totalStatsItem->fetchCount;
        $this->cooperationsCount = $totalStatsItem->cooperationsCount;
        $this->botCount = $totalStatsItem->botCount;
        $this->foodsaverCount = $totalStatsItem->foodsaverCount;
        $this->fairteilerCount = $totalStatsItem->fairteilerCount;
        $this->totalBaskets = $totalBaskets;
        $this->avgWeeklyBaskets = $avgWeeklyBaskets;
        $this->countAllFoodsaver = $countAllFoosharer;
        $this->countActiveFoodSharePoints = $countActiveFoodSharePoints;
        $this->avgDailyFetchCount = $avgDailyFetchCount;
    }
}
