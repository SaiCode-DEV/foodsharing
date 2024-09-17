<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Banana\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\DTO\FoodsaverForAvatar;

class Banana
{
    public string $message;

    /**
     * May represent the sender or recipient depending on the context.
     */
    public FoodsaverForAvatar $user;

    /**
     * Time the banana was given.
     */
    public DateTime $time;

    public static function createFromArray(array $data): Banana
    {
        $banana = new self();
        $banana->message = $data['msg'];
        $banana->user = FoodsaverForAvatar::createFromArray($data);
        $banana->time = new DateTime($data['time']);

        return $banana;
    }
}
