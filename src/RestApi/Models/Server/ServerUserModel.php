<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Server;

use OpenApi\Attributes as OA;

class ServerUserModel
{
    #[OA\Property(type: 'integer')]
    public int $id;

    #[OA\Property(type: 'string')]
    public string $firstname;

    #[OA\Property(type: 'string')]
    public string $lastname;

    #[OA\Property(type: 'boolean')]
    public bool $may;

    #[OA\Property(type: 'integer')]
    public int $homeRegionId;

    #[OA\Property(type: 'boolean')]
    public bool $hasMailbox;

    #[OA\Property(type: 'boolean')]
    public bool $isFoodsaver;

    #[OA\Property(type: 'boolean')]
    public bool $verified;

    #[OA\Property(type: 'string')]
    public string $avatar;
}
