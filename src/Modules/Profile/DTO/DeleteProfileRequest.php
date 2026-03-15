<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Profile\DTO;

use OpenApi\Attributes as OA;

class DeleteProfileRequest
{
    #[OA\Property(example: 'This account was deleted because...')]
    public ?string $reason = null;

    #[OA\Property(example: 'Password123!')]
    public ?string $password = null;
}
