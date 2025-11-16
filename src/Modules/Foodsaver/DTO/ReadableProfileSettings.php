<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Gender;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use OpenApi\Annotations as OA;

class ReadableProfileSettings
{
    /**
     * User identifier in foodsharing.
     */
    public int $id;

    /**
     * First name of the user.
     *
     * @OA\Property(example="Peter", maxLength=120)
     */
    public ?string $firstName;

    /**
     * Last name of the user.
     *
     * @OA\Property(example="Miller", maxLength=120)
     */
    public ?string $lastName;

    public ?string $photo;
    public Role $role;
    public string $position;
    public int $regionId;
    public ?string $regionName;
    public int $gender;
    public ?DateTime $birthday;
    public ?string $mobile;
    public ?string $phone;
    public ?Address $address;
    public ?GeoLocation $coordinate;
    public string $aboutMePublic;
    public ?string $aboutMeInternal;
    public bool $noAutoDelete;
    public ?int $targetRole;
    public bool $isOnTeamPage;
    public bool $mayChangeVerifiedData;
    public bool $mayChangeEmailImmediately;
    public ?array $sleepingData;
    public ?array $businessCardData;

    public function __construct(
        int $id = 0,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $photo = null,
        Role $role = Role::FOODSHARER,
        string $position = null,
        int $regionId = 0,
        int $gender = Gender::NOT_SELECTED,
        ?DateTime $birthday = null,
        ?string $mobile = null,
        ?string $phone = null,
        ?Address $location = null,
        ?GeoLocation $coordinate = null,
        string $aboutMePublic = '',
        ?string $aboutMeInternal = null,
        bool $noAutoDelete = false,
        ?string $regionName = null,
        ?int $targetRole = null,
        bool $isOnTeamPage = false,
        bool $mayChangeVerifiedData = false,
        bool $mayChangeEmailImmediately = false,
        ?array $sleepingData = null,
        ?array $businessCardData = null
    ) {
        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->photo = $photo;
        $this->role = $role;
        $this->position = $position;
        $this->regionId = $regionId;
        $this->regionName = $regionName;
        $this->gender = $gender;
        $this->birthday = $birthday;
        $this->mobile = $mobile;
        $this->phone = $phone;
        $this->address = $location;
        $this->coordinate = $coordinate;
        $this->aboutMePublic = $aboutMePublic;
        $this->aboutMeInternal = $aboutMeInternal;
        $this->noAutoDelete = $noAutoDelete;
        $this->targetRole = $targetRole;
        $this->isOnTeamPage = $isOnTeamPage;
        $this->mayChangeVerifiedData = $mayChangeVerifiedData;
        $this->mayChangeEmailImmediately = $mayChangeEmailImmediately;
        $this->sleepingData = $sleepingData;
        $this->businessCardData = $businessCardData;
    }
}
