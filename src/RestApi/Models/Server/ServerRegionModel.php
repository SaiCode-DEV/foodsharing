<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Server;

use OpenApi\Attributes as OA;

class ServerRegionModel
{
    #[OA\Property(type: 'integer')]
    public int $id;

    #[OA\Property(type: 'string')]
    public string $name;

    #[OA\Property(type: 'integer')]
    public int $type;

    #[OA\Property(type: 'integer', nullable: true)]
    public ?int $parent_id;

    #[OA\Property(type: 'boolean')]
    public bool $hasAchievements;

    #[OA\Property(type: 'boolean')]
    public bool $mayHandleFoodsaverRegionMenu;

    #[OA\Property(type: 'boolean')]
    public bool $hasConference;

    #[OA\Property(type: 'boolean')]
    public bool $hasResources;

    #[OA\Property(type: 'boolean')]
    public bool $isAdmin;

    #[OA\Property(type: 'boolean')]
    public bool $mayAccessReports;

    #[OA\Property(type: 'boolean')]
    public bool $isReportAdmin;

    #[OA\Property(type: 'boolean')]
    public bool $isArbitrationAdmin;

    #[OA\Property(type: 'boolean')]
    public bool $maySetRegionPin;

    #[OA\Property(type: 'integer')]
    public ?int $mailboxId;
}
