<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\SupportPage;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class TicketModel
{
    #[OA\Property(description: 'Email address of the user who sent the request.')]
    #[Assert\Length(min: 1)]
    #[Assert\Email()]
    public string $emailAddress;

    #[OA\Property(description: 'Subject of the support ticket.')]
    #[Assert\Length(min: 1)]
    public string $subject;

    #[OA\Property(description: 'The content of the ticket.')]
    #[Assert\Length(min: 1)]
    public string $body;

    #[OA\Property(description: 'The first name of the user')]
    public string $firstName;

    #[OA\Property(description: 'Optional list of attached files')]
    #[Type('array<Foodsharing\RestApi\Models\SupportPage\TicketAttachment>')]
    public array $attachments;

    #[OA\Property(description: 'The user agent (browser version and OS) of the user')]
    public ?string $userAgent = null;
}
