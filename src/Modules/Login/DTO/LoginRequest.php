<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class LoginRequest
{
    #[Assert\NotBlank]
    #[OA\Property(description: 'email', example: 'user@example.com')]
    public string $email;

    #[Assert\NotBlank]
    #[OA\Property(description: 'password', example: 'Password123!')]
    public string $password;

    #[OA\Property(description: '2FA code (optional)', example: null)]
    public ?string $code = null;

    #[OA\Property(description: 'Whether to keep the session alive for longer', example: false)]
    public bool $rememberMe = false;
}
