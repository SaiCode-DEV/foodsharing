<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Server;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class ServerDataModel
{
    #[OA\Property(ref: new Model(type: ServerUserModel::class))]
    public ServerUserModel $user;

    #[OA\Property(type: 'object')]
    public object $permissions;

    #[OA\Property(type: 'string')]
    public string $page;

    #[OA\Property(type: 'string')]
    public string $subPage;

    #[OA\Property(type: 'object')]
    public object $locations;

    #[OA\Property(type: 'string')]
    public string $ravenConfig;

    #[OA\Property(type: 'string')]
    public string $version;

    #[OA\Property(type: 'boolean')]
    public bool $isDev;

    #[OA\Property(type: 'boolean')]
    public bool $isTest;

    #[OA\Property(type: 'string')]
    public string $locale;

    #[OA\Property(type: 'string')]
    public string $geoapifyApiKey;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ServerGroupModel::class)))]
    public array $groups;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ServerRegionModel::class)))]
    public array $regions;
}
