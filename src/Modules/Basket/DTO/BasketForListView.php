<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Basket\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class BasketForListView
{
    public int $id;
    public string $description;
    public ?string $picture = null;
    public DateTime $until;
    public float $distanceInKm;
    public Profile $creator;

    public static function createFromArray(array $data): BasketForListView
    {
        $basket = new BasketForListView();
        $basket->id = $data['id'];
        $basket->description = $data['description'];
        $picture = json_decode($data['picture'] ?? '', true);
        $basket->picture = is_array($picture) ? ($picture[0] ?? null) : $data['picture'];
        $basket->until = new Carbon($data['until']);
        $basket->distanceInKm = $data['distance_in_km'];
        $basket->creator = new Profile($data, 'fs_');

        return $basket;
    }
}
