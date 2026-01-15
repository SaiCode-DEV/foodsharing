<?php

namespace Foodsharing\Modules\Message\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class EditChatData
{
    #[Assert\NotBlank]
    public string $name;
}
