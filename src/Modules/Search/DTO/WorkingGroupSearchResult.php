<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\DTO\FoodsaverForAvatar;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class WorkingGroupSearchResult extends SearchResult
{
    /**
     * Email address of the working group.
     *
     * Does not include the "@foodsharing.network" ending.
     *
     * @OA\Property(example="bildung.muenster")
     */
    public string $email;

    /**
     * Unique identifier of the working groups parent region.
     *
     * @OA\Property(example=1)
     */
    public int $parent_id;

    /**
     * Name of the working groups parent region.
     *
     * @OA\Property(example="Münster")
     */
    public string $parent_name;

    /**
     * Whether the searching user is member in the working group.
     *
     * @OA\Property(example=true)
     */
    public bool $is_member;

    /**
     * Whether the searching user is admin in the working group.
     *
     * @OA\Property(example=false)
     */
    public bool $is_admin;

    /**
     * Admins of the working group.
     *
     * @var array<FoodsaverForAvatar> Array of Admins
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=FoodsaverForAvatar::class))
     * )
     */
    public array $admins;

    public static function createFromArray(array $data): WorkingGroupSearchResult
    {
        $result = new WorkingGroupSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->email = str_replace('@foodsharing.network', '', $data['email']);
        $result->parent_id = $data['parent_id'];
        $result->parent_name = $data['parent_name'];
        $result->is_member = boolval($data['is_member']);
        $result->is_admin = boolval($data['is_admin']);
        $result->admins = self::formatUserList($data, 'admin');
        $result->setSearchString($data);

        return $result;
    }
}
