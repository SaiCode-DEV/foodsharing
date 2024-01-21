<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Activities;

use Foodsharing\Modules\Activity\DTO\ActivityFilter;
use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'A list of all activities that the logged-in user has hidden')]
class ActivityModel
{
    #[OA\Property(title: 'The type of activity', type: 'string')]
    #[Type('string')]
    public readonly string $index;

    #[OA\Property(title: 'name of the activity', type: 'string')]
    #[Type('string')]
    public readonly string $name;

    #[OA\Property(title: 'short name of the activity', type: 'string')]
    #[Type('string')]
    public readonly string $shortName;

    #[OA\Property(
        title: 'the individual elements of the activity',
        type: 'array',
        items: new OA\Items(ref: new Model(type: ActivityFilter::class))
    )]
    #[Type('array<Foodsharing\Modules\Activity\DTO\ActivityFilter>')]
    public readonly array $items;

    public function __construct(string $index, string $name, string $shortName, array $items)
    {
        $this->index = $index;
        $this->name = $name;
        $this->shortName = $shortName;
        $this->items = $items;
    }
}
