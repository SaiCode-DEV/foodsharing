<?php

namespace Foodsharing\RestApi\Models\Newsletter;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class NewsletterTestEmail
{
    #[OA\Property(description: 'The email address to which the newsletter will be sent.', type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $address;

    #[OA\Property(description: 'Subject of the email.', type: 'string')]
    #[Assert\NotBlank]
    public string $subject;

    #[OA\Property(description: 'Content of the email.', type: 'string')]
    #[Assert\NotBlank]
    public string $message;
}
