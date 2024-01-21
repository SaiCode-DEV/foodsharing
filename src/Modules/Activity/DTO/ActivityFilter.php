<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Activity\DTO;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

class ActivityFilter
{
    #[OA\Property(title: 'id of the item', type: 'integer')]
    #[Type('integer')]
    public int $id;

    #[OA\Property(title: 'name of the item', type: 'string')]
    #[Type('string')]
    public string $name;

    #[OA\Property(title: 'is this included', type: 'boolean')]
    #[Type('boolean')]
    public bool $included;

    public static function create(int $id, string $name, bool $included): ActivityFilter
    {
        $option = new ActivityFilter();
        $option->id = $id;
        $option->name = $name;
        $option->included = $included;

        return $option;
    }
}
