<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

use JMS\Serializer\Annotation\Type;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contains all the information that is needed for creating a new food share point.
 */
class FoodSharePointEditData extends FoodSharePointForCreation
{
    #[OA\Property(
        description: 'IDs of all users who are responsible for the food share point',
        type: 'array',
        items: new OA\Items(type: 'integer', example: 1))
    ]
    #[Assert\Count(min: 1)]
    #[Assert\All(new Assert\Positive())]
    #[Type('array<int>')]
    public array $managerIds;
}
