<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\DTO\FoodsaverForAvatar;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class RegionSearchResult extends SearchResult
{
    /**
     * Email address of the region.
     *
     * Includes the '@...' mail ending.
     *
     * @OA\Property(example="muenster@foodsharing.network")
     */
    public string $email;

    /**
     * Unique identifier of the regions parent region.
     *
     * @OA\Property(example=1)
     */
    public int $parent_id;

    /**
     * Name of the regions parent region.
     *
     * @OA\Property(example="Nordrhein-Westfalen")
     */
    public string $parent_name;

    /**
     * Whether the searching user is member in the region.
     *
     * @OA\Property(example=true)
     */
    public bool $is_member;

    /**
     * Ambassadors of the region.
     *
     * @var array<FoodsaverForAvatar> Array of Ambassadors
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=FoodsaverForAvatar::class))
     * )
     */
    public array $ambassadors;

    public static function createFromArray(array $data): RegionSearchResult
    {
        $result = new RegionSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->email = $data['email'] ?? '';
        if (!empty($data['email']) && !str_contains((string)$data['email'], '@')) {
            $result->email .= '@' . PLATFORM_MAILBOX_HOST;
        }
        $result->parent_id = $data['parent_id'];
        $result->parent_name = $data['parent_name'];
        $result->is_member = boolval($data['is_member']);
        $result->ambassadors = self::formatUserList($data, 'ambassador');
        $result->setSearchString($data);

        return $result;
    }
}
