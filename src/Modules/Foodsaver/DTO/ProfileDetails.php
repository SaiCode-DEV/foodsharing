<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Foodsaver\DTO;

use DateTime;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\RestApi\Models\Group\UserGroupModel;
use Foodsharing\RestApi\Models\Region\UserRegionModel;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class ProfileDetails
{
    #[OA\Property(example: 123)]
    public int $id;

    #[OA\Property(description: 'Whether current session has foodsaver role')]
    public bool $foodsaver;

    #[OA\Property(description: 'Whether user is verified')]
    public bool $isVerified;

    #[OA\Property(description: 'Home region id')]
    public ?int $regionId = null;

    #[OA\Property(description: 'Whether user is sleeping')]
    public bool $isSleeping;

    #[OA\Property(description: 'Home region name')]
    public ?string $regionName = null;

    #[OA\Property(description: 'Public about-me text')]
    public string $aboutMePublic;

    #[OA\Property(description: 'Personal mailbox id', example: 5)]
    public ?int $mailboxId = null;

    #[OA\Property(description: 'Has calendar API token')]
    public bool $hasCalendarToken;

    #[OA\Property(description: 'First name', example: 'Anna')]
    public string $firstname;

    #[OA\Property(description: 'Last name', example: 'Muster')]
    public string $lastname;

    #[OA\Property(description: 'Gender')]
    public int $gender;

    #[OA\Property(description: 'Photo url or path')]
    public ?string $photo = null;

    #[OA\Property(description: 'Date of last pass')]
    public ?DateTime $lastPassDate = null;

    #[OA\Property(description: 'Pass valid until')]
    public ?DateTime $lastPassUntilValid = null;

    #[OA\Property(description: 'Days until pass validity ends')]
    public ?int $lastPassUntilValidInDays = null;

    public array $stats = [];

    public array $permissions = [];

    public ?GeoLocation $coordinates = null;

    public ?string $address = null;
    public ?string $city = null;
    public ?string $postcode = null;
    public ?string $email = null;
    public ?string $landline = null;
    public ?string $mobile = null;
    public ?DateTime $birthday = null;
    public ?string $aboutMeIntern = null;
    public ?int $role = null;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: UserRegionModel::class)))]
    public ?array $regions = null;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: UserGroupModel::class)))]
    public ?array $groups = null;

    public ?string $position = null;
}
