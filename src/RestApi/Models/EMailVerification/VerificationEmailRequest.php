<?php

namespace Foodsharing\RestApi\Models\EMailVerification;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class VerificationEmailRequest
{
    #[OA\Property(description: 'The email address to which the new verification email will be sent.', type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $address;
}
