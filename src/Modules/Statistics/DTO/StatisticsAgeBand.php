<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Statistics\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Represents one entry in the result of age band Region query.')]
class StatisticsAgeBand
{
    #[OA\Property(description: 'The age Band', type: 'string', format: 'from-to')]
    #[Type('string')]
    public string $ageBand;

    #[OA\Property(description: 'number of persons in the age group', type: 'integer')]
    #[Type('integer')]
    public int $numberOfAgeBand;

    public static function create(string $ageBand, int $numberOfAgeBand): StatisticsAgeBand
    {
        $statisticsAgeBand = new StatisticsAgeBand();
        $statisticsAgeBand->ageBand = $ageBand;
        $statisticsAgeBand->numberOfAgeBand = $numberOfAgeBand;

        return $statisticsAgeBand;
    }
}
