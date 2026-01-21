<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\FoodSharePoint\DTO\FoodSharePoint;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

/**
 * Contains all the information that is needed for creating a new food share point.
 */
class FoodSharePointDetails extends FoodSharePoint
{
    public string $regionName;
    public int $followerCount;

    #[OA\Property(
        description: 'IDs of all users who are responsible for the food share point',
        type: 'array',
        items: new OA\Items(ref: new Model(type: Profile::class))),
    ]
    public array $managers;
}
