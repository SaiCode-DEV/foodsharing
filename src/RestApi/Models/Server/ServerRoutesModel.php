<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Server;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class ServerRoutesModel
{
    #[OA\Property(type: 'string', description: 'Application version/revision')]
    public string $version;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ServerRouteModel::class)))]
    public array $api;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ServerRouteModel::class)))]
    public array $routes;
}
