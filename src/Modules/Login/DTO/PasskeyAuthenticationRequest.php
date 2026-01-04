<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Login\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class PasskeyAuthenticationRequest
{
    #[Assert\NotBlank(message: 'Credential data is required')]
    #[OA\Property(description: 'The assertion data from the authenticator')]
    public mixed $credential;
}
