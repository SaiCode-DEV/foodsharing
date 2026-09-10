<?php

namespace Foodsharing\Modules\Message\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChatMessage
{
    #[Assert\NotBlank]
    public string $body;

    /**
     * Client-generated idempotency key (12 random hex chars). A retry of a failed
     * send reuses the same key so the server can deduplicate it instead of storing
     * a second message. Optional: absent for older clients and server-initiated sends.
     */
    #[Assert\Regex('/^[0-9a-f]{12}$/')]
    public ?string $clientKey = null;
}
