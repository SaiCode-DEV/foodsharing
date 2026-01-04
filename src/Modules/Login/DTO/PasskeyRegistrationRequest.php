<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class PasskeyRegistrationRequest
{
    #[Assert\NotBlank(message: 'Credential data is required')]
    #[OA\Property(description: 'The credential data from the authenticator')]
    public mixed $credential;

    #[OA\Property(description: 'Optional name for the passkey', example: 'My YubiKey')]
    public ?string $name = null;
}
