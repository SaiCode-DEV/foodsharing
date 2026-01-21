<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

class FoodSharePointForListView
{
    public int $id;
    public string $name;
    public ?string $picture;

    public static function create(int $id, string $name, ?string $picture): self
    {
        $foodSharePoint = new self();
        $foodSharePoint->id = $id;
        $foodSharePoint->name = $name;
        $foodSharePoint->picture = $picture ?: null;

        return $foodSharePoint;
    }
}
