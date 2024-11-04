<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class BasketRequest
{
    public Profile $user;
    public int $time;

    public static function createFromArray(array $data): BasketRequest
    {
        $request = new BasketRequest();
        $request->user = new Profile($data, 'fs_');
        $request->time = $data['time_ts'];

        return $request;
    }
}
