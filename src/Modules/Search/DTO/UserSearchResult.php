<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Attributes as OA;

class UserSearchResult extends SearchResult
{
    #[OA\Property(example: null, description: 'URL of the users avatar. May be null.')]
    public ?string $avatar = null;

    #[OA\Property(example: 'Mustermann', description: 'Last name of the user.')]
    public ?string $lastName = null;

    #[OA\Property(example: '+49 1234 56789', description: 'Mobile phone number of the user.')]
    public ?string $mobile = null;

    #[OA\Property(description: 'Whether the searching user and the found user are buddies.')]
    public bool $isBuddy;

    #[OA\Property(description: 'Whether the user is verified.')]
    public bool $isVerified;

    #[OA\Property(description: 'Unique identifier of the users home region.')]
    public int $regionId;

    #[OA\Property(example: 'Münster', description: 'Name of the users home region.')]
    public string $regionName;

    #[OA\Property(example: 'max@mustermann.com', description: 'The users private mail address.')]
    public ?string $email = null;

    public static function createFromArray(array $data): UserSearchResult
    {
        $result = new UserSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->avatar = $data['photo'];
        $result->regionId = $data['region_id'];
        $result->regionName = $data['region_name'];
        $result->lastName = $data['last_name'];
        $result->mobile = $data['mobile'];
        $result->isBuddy = (bool)$data['is_buddy'];
        $result->isVerified = (bool)$data['is_verified'];
        $result->email = $data['email'] ?? null;
        $result->setSearchString($data);

        return $result;
    }
}
