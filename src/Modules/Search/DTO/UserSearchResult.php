<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Annotations as OA;

class UserSearchResult extends SearchResult
{
    /**
     * URL of the users avatar.
     *
     * May be null.
     *
     * @OA\Property(example=null)
     */
    public ?string $avatar = null;

    /**
     * Last name of the user.
     *
     * @OA\Property(example="Mustermann")
     */
    public ?string $last_name = null;

    /**
     * Mobile phone number of the user.
     *
     * @OA\Property(example="+49 1234 56789")
     */
    public ?string $mobile = null;

    /**
     * Whether the searching user and the found user are buddies.
     *
     * @OA\Property(example=true)
     */
    public bool $is_buddy;

    /**
     * Whether the user is verified.
     *
     * @OA\Property(example=true)
     */
    public bool $is_verified;

    /**
     * Unique identifier of the users home region.
     *
     * @OA\Property(example=1)
     */
    public int $region_id;

    /**
     * Name of the users home region.
     *
     * @OA\Property(example="Münster")
     */
    public string $region_name;

    /**
     * Name of the users home region.
     *
     * @OA\Property(example="Münster")
     */
    public ?string $email = null;

    public static function createFromArray(array $data): UserSearchResult
    {
        $result = new UserSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->avatar = $data['photo'];
        $result->region_id = $data['region_id'];
        $result->region_name = $data['region_name'];
        $result->last_name = $data['last_name'];
        $result->mobile = $data['mobile'];
        $result->is_buddy = (bool)$data['is_buddy'];
        $result->is_verified = (bool)$data['is_verified'];
        $result->email = $data['email'] ?? null;
        $result->setSearchString($data);

        return $result;
    }
}
