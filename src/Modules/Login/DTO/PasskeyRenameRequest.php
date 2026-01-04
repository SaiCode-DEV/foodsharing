<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class PasskeyRenameRequest
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 1, max: 255)]
    #[OA\Property(description: 'New name for the passkey', example: 'My iPhone')]
    public string $name;
}
