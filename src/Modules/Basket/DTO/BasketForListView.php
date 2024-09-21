<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Foodsharing\Modules\Foodsaver\Profile;

class BasketForListView
{
    public int $id;
    public string $description;
    public ?string $picture;
    public int $until;
    public float $distance;
    public Profile $creator;

    public static function createFromArray(array $data): BasketForListView
    {
        $basket = new BasketForListView();
        $basket->id = $data['id'];
        $basket->description = $data['description'];
        $picture = json_decode($data['picture'] ?? '', true);
        $basket->picture = is_array($picture) ? ($picture[0] ?? null) : $data['picture'];
        $basket->until = $data['until_ts'];
        $basket->distance = $data['distance'];
        $basket->creator = new Profile($data, 'fs_');

        return $basket;
    }
}
