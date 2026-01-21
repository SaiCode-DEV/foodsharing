<?php

namespace Foodsharing\RestApi\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RequiredMessage
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1)]
    public string $message;
}
