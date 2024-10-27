<?php

namespace Foodsharing\Modules\Store\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class StoreApplicationMessage
{
    #[Assert\Length(min: 1)]
    public ?string $message;
}
