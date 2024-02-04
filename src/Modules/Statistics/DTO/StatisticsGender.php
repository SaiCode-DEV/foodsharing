<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Statistics\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Represents one entry in the result of gender Region query.')]
class StatisticsGender
{
    #[OA\Property(
        description: 'Gender of the user.<br>
                      0 = not selected<br>
                      1 = Male<br>
                      2 = Female<br>
                      3 = Divers',
        type: 'integer',
    )]
    #[Type('integer')]
    public int $gender;

    #[OA\Property(description: 'count of gender', type: 'integer')]
    #[Type('integer')]
    public int $numberOfGender;

    public static function create(int $gender, int $numberOfGender): StatisticsGender
    {
        $statisticsGender = new StatisticsGender();
        $statisticsGender->gender = $gender;
        $statisticsGender->numberOfGender = $numberOfGender;

        return $statisticsGender;
    }
}
