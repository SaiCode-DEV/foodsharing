<?php

namespace Foodsharing\Modules\Foodsaver\DTO;

use OpenApi\Annotations as OA;

/**
 * Class that represents the data of a foodsaver required to display it's avatar somewhere.
 *
 * @OA\Schema(required={"id", "name"})
 */
class FoodsaverForAvatar
{
    /**
     * Unique identifier of the foodsaver.
     *
     * @OA\Property(example=1)
     */
    public int $id;

    /**
     * Name of the Foodsaver.
     *
     * Might include the last name.
     *
     * @OA\Property(example="Max Mustermann")
     */
    public string $name;

    /**
     * URL of the foodsavers avatar image.
     *
     * @OA\Property(example="/api/uploads/bc476952-08be-45a7-b670-db27c966c9c2")
     */
    public ?string $avatar = null;

    /**
     * Converts an dictionary into an FoodsaverForAvatar object.
     */
    public static function createFromArray(array $data, array $keys = []): FoodsaverForAvatar
    {
        global $container;
        $keys = array_merge(['id' => 'id', 'name' => 'name', 'avatar' => 'photo'], $keys);
        $obj = new FoodsaverForAvatar();
        $obj->id = $data[$keys['id']];
        $obj->name = $data[$keys['name']] ?? $container->get('translator')->trans('forum.deleted_user');
        $obj->avatar = $data[$keys['avatar']];

        return $obj;
    }
}
