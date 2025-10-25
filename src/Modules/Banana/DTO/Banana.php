<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Banana\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class Banana
{
    public string $message;

    /**
     * May represent the sender or recipient depending on the context.
     */
    public Profile $user;

    /**
     * Time the banana was given.
     */
    public DateTime $time;

    public static function create(string $message, Profile $user, DateTime $time): Banana
    {
        $banana = new self();
        $banana->message = $message;
        $banana->user = $user;
        $banana->time = $time;

        return $banana;
    }
}
