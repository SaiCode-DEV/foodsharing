<?php

namespace Foodsharing\Modules\Store\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class StoreApplicationMessage
{
    #[Assert\Length(max: 300, min: 1)] // allow null but no empty string
    public ?string $message;
}
