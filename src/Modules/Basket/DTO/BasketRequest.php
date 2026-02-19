<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class BasketRequest
{
    public function __construct(public int $basketId, public Profile $user, public DateTime $requestedAt)
    {
    }
}
