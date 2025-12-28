<?php

namespace Foodsharing\Modules\Banana\DTO;

use Foodsharing\RestApi\BananaRestController;
use Symfony\Component\Validator\Constraints as Assert;

class BananaMessage
{
    #[Assert\Length(min: BananaRestController::MIN_RATING_MESSAGE_LENGTH)]
    public string $message;
}
