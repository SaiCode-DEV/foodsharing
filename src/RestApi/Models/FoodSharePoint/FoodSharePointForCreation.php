<?php

namespace Foodsharing\RestApi\Models\FoodSharePoint;

use Foodsharing\Modules\Core\DTO\GeoLocation;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contains all the information that is needed for creating a new food share point.
 */
class FoodSharePointForCreation
{
    #[OA\Property(example: 1)]
    public int $regionId;

    #[OA\Property(example: 'Test-Fairteiler')]
    #[Assert\NotBlank]
    public string $name;

    #[OA\Property(example: 'Test-Beschreibung')]
    public string $description;

    #[OA\Property(example: '/api/uploads/12345678')]
    public ?string $picture = null;

    #[OA\Property(example: 'Beispielstraße 1')]
    public string $address;

    #[OA\Property(example: '12345')]
    public string $postalCode;

    #[OA\Property(example: 'Musterstadt')]
    public string $city;

    #[OA\Property]
    public GeoLocation $location;
}
