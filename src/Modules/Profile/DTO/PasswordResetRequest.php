<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Profile\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequest
{
    #[OA\Property(example: 'newpassword123')]
    #[Assert\NotBlank]
    public string $password;

    #[OA\Property(example: '720312')]
    public ?string $totpCode;

    #[OA\Property(example: '96D2-DB1C-C203')]
    #[Assert\NotBlank]
    public string $resetToken;
}
