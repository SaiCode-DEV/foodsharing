<?php

namespace Foodsharing\Modules\Message\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class EditChatData
{
    #[Assert\Length(min: 1)]
    public ?string $name;
}
