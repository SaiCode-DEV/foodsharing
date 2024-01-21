<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Activities;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A specific activity that is to be hidden')]
class ActivityFilterItem
{
    #[OA\Property(title: 'id of the item.', type: 'integer', nullable: false)]
    #[Type('int')]
    public readonly int $id;

    #[OA\Property(title: 'The activity.', type: 'string', nullable: false)]
    #[Type('string')]
    public readonly string $index;

    public function __construct(int $id, string $index)
    {
        $this->id = $id;
        $this->index = $index;
    }
}
