<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use OpenApi\Annotations as OA;

class EditableProfileDTO
{
    /**
     * User identifier in foodsharing.
     */
    public int $id = 0;

    /**
     * First name of the user.
     *
     * @OA\Property(example="Peter", maxLength=120)
     */
    public ?string $firstName = null;

    /**
     * Last name of the user.
     *
     * @OA\Property(example="Miller", maxLength=120)
     */
    public ?string $lastName = null;
    public ?int $role = null;
    public ?string $position = null;
    public ?int $regionId = null;
    public ?int $gender = null;

    public ?string $birthday = '';
    public ?string $mobile = null;
    public ?string $phone = null;
    public ?Address $location = null;
    public ?GeoLocation $coordinate = null;
    public ?string $aboutMePublic = null;
    public ?string $aboutMeInternal = null;
    public ?int $noAutoDelete = null;

    public function __construct()
    {
        $this->location = new Address();
        $this->coordinate = new GeoLocation();
    }
}
