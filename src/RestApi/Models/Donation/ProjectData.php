<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Donation;

use OpenApi\Attributes as OA;

class ProjectData
{
    #[OA\Property(description: 'Project ID', example: 12345)]
    public readonly int $id;

    #[OA\Property(description: 'Name of the project', example: 'Spendenkampagne')]
    public readonly string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
