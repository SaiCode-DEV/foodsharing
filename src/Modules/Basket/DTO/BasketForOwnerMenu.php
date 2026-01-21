<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use DateTime;

class BasketForOwnerMenu
{
    public int $id;
    public string $description;
    public ?string $picture = null;
    public DateTime $createdAt;
    public array $requests = [];

    public static function create(int $id, string $description, ?string $picture, DateTime $createdAt): BasketForOwnerMenu
    {
        $basket = new BasketForOwnerMenu();
        $basket->id = $id;
        $basket->description = $description;
        $basket->picture = $picture;
        $basket->createdAt = $createdAt;

        return $basket;
    }
}
