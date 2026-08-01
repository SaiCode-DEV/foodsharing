<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Server;

use OpenApi\Attributes as OA;

class ServerRouteModel
{
    #[OA\Property(type: 'string', description: 'Route name')]
    public string $name;

    #[OA\Property(type: 'string', description: 'Route path pattern')]
    public string $path;

    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), description: 'HTTP methods')]
    public array $methods;

    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), description: 'Required parameters')]
    public array $parameters;
}
