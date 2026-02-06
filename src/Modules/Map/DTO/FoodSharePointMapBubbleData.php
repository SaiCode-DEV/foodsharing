<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Map\DTO;

use Foodsharing\Modules\FoodSharePoint\DTO\FoodSharePoint;
use OpenApi\Attributes as OA;

#[OA\Schema]
class FoodSharePointMapBubbleData
{
    #[OA\Property(
        description: 'The name of the Foodsharepoint',
        type: 'string',
        example: 'Demo Foodsharepoint'
    )]
    public string $name;

    #[OA\Property(
        description: 'Detailed description of the Foodsharepoint',
        type: 'string',
        example: 'This meeting is for demonstration purposes only. There is nothing to get here.'
    )]
    public string $description;

    #[OA\Property(
        description: 'The street with house number where the point is located',
        type: 'string',
        example: 'Examplestreet 25'
    )]
    public string $street;

    #[OA\Property(
        description: 'The Zip Code',
        type: 'string',
        example: '12345'
    )]
    public string $zipCode;

    #[OA\Property(
        description: 'The place where the point is located',
        type: 'string',
        example: 'Sample town'
    )]
    public string $city;

    #[OA\Property(
        description: 'Path of the header picture of the point or null',
        type: 'string',
        example: '/img/foodSharePointHead.jpg'
    )]
    public ?string $picture;

    public function __construct(FoodSharePoint $foodSharePoint)
    {
        $this->name = $foodSharePoint->name;
        $this->description = $foodSharePoint->description;
        $this->street = $foodSharePoint->address->street;
        $this->zipCode = $foodSharePoint->address->postalCode;
        $this->city = $foodSharePoint->address->city;
        $this->picture = $foodSharePoint->picture;
    }
}
