<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Contains a UUID denoting an uploaded file')]
class UUID
{
    #[OA\Property(description: 'UUID of an uploaded picture')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $uuid;
}
