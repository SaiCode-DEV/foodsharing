<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class BasketRequest
{
    public int $basketId;
    public Profile $user;
    public int $time;

    public static function create(int $basketId, Profile $profile, int $time): BasketRequest
    {
        $request = new BasketRequest();
        $request->basketId = $basketId;
        $request->user = $profile;
        $request->time = $time;

        return $request;
    }
}
