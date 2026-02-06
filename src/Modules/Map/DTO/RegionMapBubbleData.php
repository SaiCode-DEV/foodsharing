<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Map\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema]
class RegionMapBubbleData
{
    public int $id = 0;

    #[OA\Property(example: 'Göttingen')]
    public string $name;

    #[OA\Property(example: 'Wilkommen in **Göttingen**!')]
    public string $description;

    public static function create(int $id, string $name, string $description)
    {
        $instance = new self();
        $instance->id = $id;
        $instance->name = $name;
        $instance->description = $description;

        return $instance;
    }
}
