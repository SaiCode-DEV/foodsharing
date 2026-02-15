<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Profile\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class EmailAddress
{
    #[OA\Property(example: 'user@example.com')]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
