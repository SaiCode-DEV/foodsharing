<?php

namespace Foodsharing\Modules\Message\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChatMessage
{
    #[Assert\NotBlank]
    public string $body;
}
