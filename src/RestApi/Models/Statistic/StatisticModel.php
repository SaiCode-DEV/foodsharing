<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Statistic;

use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Includes general statistical information and activity ladder of regions and foodsavers')]
class StatisticModel
{
    #[OA\Property(
        ref: new Model(type: GeneralStatistic::class),
        description: 'A list of overall statistical data'
    )]
    #[Type('Foodsharing\RestApi\Models\Statistic\GeneralStatistic')]
    public readonly GeneralStatistic $generalStatistic;

    #[OA\Property(
        ref: new Model(type: PickupModel::class),
        description: 'Ranking of the most active regions'
    )]
    #[Type('Foodsharing\RestApi\Models\Statistic\PickupModel')]
    public readonly PickupModel $regionsActivity;

    #[OA\Property(
        ref: new Model(type: PickupModel::class),
        description: 'Ranking of the most active foodsaver'
    )]
    #[Type('Foodsharing\RestApi\Models\Statistic\PickupModel')]
    public readonly PickupModel $foodsaverActivity;

    public function __construct(
        GeneralStatistic $generalStatistic,
        PickupModel $regionsActivity,
        PickupModel $foodsaverActivity
    ) {
        $this->generalStatistic = $generalStatistic;
        $this->regionsActivity = $regionsActivity;
        $this->foodsaverActivity = $foodsaverActivity;
    }
}
