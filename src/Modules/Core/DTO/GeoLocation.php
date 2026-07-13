<?php

namespace Foodsharing\Modules\Core\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Describes a location by its coordinates, so that a representation on a map is possible.
 */
class GeoLocation
{
    /**
     * Latitude of the location.
     */
    #[OA\Property(example: 52.5)]
    #[Assert\Range(min: -90, max: 90)]
    #[Assert\NotBlank]
    public float $lat = 0;

    /**
     * Longitude of the location.
     */
    #[OA\Property(example: 13.4)]
    #[Assert\Range(min: -180, max: 180)]
    #[Assert\NotBlank]
    public float $lon = 0;

    public function __construct(float $lat, float $lon)
    {
        $this->lat = $lat;
        $this->lon = $lon;
    }
}
