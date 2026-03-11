<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use OpenApi\Annotations as OA;

class SimplifiedUserSearchResult
{
    /**
     * Unique identifier of the user represented by the search result.
     *
     * @OA\Property(example=123)
     */
    public int $id;

    /**
     * String representation of the resulting user.
     *
     * @OA\Property(example="Max (123)")
     */
    public string $value;

    public static function fromUserSearchResult(UserSearchResult $user): SimplifiedUserSearchResult
    {
        $result = new SimplifiedUserSearchResult();
        $result->id = $user->id;
        $name = empty($user->lastName) ? $user->name : "{$user->name} {$user->lastName}";
        $result->value = "{$name} ({$user->id})";

        return $result;
    }
}
