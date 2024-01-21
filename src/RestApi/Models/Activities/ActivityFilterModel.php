<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Activities;

use JMS\Serializer\Annotation\Type;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'All activities that are to be hidden in the overview')]
class ActivityFilterModel
{
    #[OA\Property(
        title: 'List of all activities not to be displayed.',
        type: 'array',
        items: new OA\Items(ref: new Model(type: ActivityFilterItem::class))
    )]
    #[Type('array<Foodsharing\RestApi\Models\Activities\ActivityFilterItem>')]
    public readonly array $excluded;

    public function __construct(array $excluded)
    {
        $this->excluded = $excluded;
    }
}
