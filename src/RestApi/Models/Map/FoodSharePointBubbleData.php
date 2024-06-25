<?php

declare(strict_types=1);

namespace Foodsharing\RestApi\Models\Map;

use OpenApi\Attributes as OA;

#[OA\Schema]
class FoodSharePointBubbleData
{
    #[OA\Property(
        description: 'The name of the Foodsharepoint',
        type: 'string',
        example: 'Demo Foodsharepoint'
    )]
    public readonly string $name;

    #[OA\Property(
        description: 'Detailed description of the Foodsharepoint',
        type: 'string',
        example: 'This meeting is for demonstration purposes only. There is nothing to get here.'
    )]
    public readonly string $description;

    #[OA\Property(
        description: 'The street with house number where the point is located',
        type: 'string',
        example: 'Examplestreet 25'
    )]
    public readonly string $street;

    #[OA\Property(
        description: 'The Zip Code',
        type: 'string',
        example: '12345'
    )]
    public readonly string $zipCode;

    #[OA\Property(
        description: 'The place where the point is located',
        type: 'string',
        example: 'Sample town'
    )]
    public readonly string $city;

    #[OA\Property(
        description: 'Path of the header picture of the point or null',
        type: 'string',
        example: '/img/foodSharePointHead.jpg'
    )]
    public readonly ?string $picture;

    public function __construct(array $foodSharePoint)
    {
        $this->name = $foodSharePoint['name'];
        $this->description = $foodSharePoint['desc'];
        $this->street = $foodSharePoint['anschrift'];
        $this->zipCode = $foodSharePoint['plz'];
        $this->city = $foodSharePoint['ort'];
        $this->picture = $foodSharePoint['picture'];
    }
}
