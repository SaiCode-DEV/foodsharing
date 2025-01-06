<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

class BasketForOwnerMenu
{
    public int $id;
    public string $description;
    public ?string $picture = null;
    public int $createdAt;
    public array $requests = [];

    public static function createFromArray(array $data): BasketForOwnerMenu
    {
        $basket = new BasketForOwnerMenu();
        $basket->id = $data['id'];
        $basket->description = $data['description'];
        $picture = json_decode($data['picture'] ?? '', true);
        $basket->picture = is_array($picture) ? ($picture[0] ?? null) : $data['picture'];
        $basket->createdAt = $data['time_ts'];

        return $basket;
    }
}
