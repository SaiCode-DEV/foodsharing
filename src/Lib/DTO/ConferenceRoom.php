<?php

declare(strict_types=1);

namespace Foodsharing\Lib\DTO;

class ConferenceRoom
{
    public string $id;
    public string $dialin;

    public static function create(string $id, string $dialin): self
    {
        $room = new self();
        $room->id = $id;
        $room->dialin = $dialin;

        return $room;
    }
}
