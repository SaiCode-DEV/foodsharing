<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Achievement\DTO;

use OpenApi\Attributes as OA;

class AwardedAchievementDetails
{
    #[OA\Property(example: 'infinite', description: 'Date until which this is valid, or \'infinite\'')]
    public ?string $validUntil = null;

    #[OA\Property(description: 'Optional notice associated with the awarded achievement')]
    public ?string $notice = null;
}
