<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\DTO\FoodsaverForAvatar;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class ChatSearchResult extends SearchResult
{
    /**
     * Time of the last message was sent in the chat.
     *
     * @OA\Property(example="2023-10-04 13:38:03")
     */
    public ?string $last_message_date = null;

    /**
     * Unique identifier of the foodsaver who last sent a message in the chat.
     *
     * @OA\Property(example=1)
     */
    public ?int $last_foodsaver_id = null;

    /**
     * Name of the foodsaver who last sent a message in the chat.
     *
     * @OA\Property(example="Max")
     */
    public ?string $last_foodsaver_name = null;

    /**
     * Last message that was sent in the chat.
     *
     * @OA\Property(example="Have a nice day!")
     */
    public ?string $last_message = null;

    /**
     * Members of the chat.
     *
     * This includes at most 5 members to be displayed in the search result.
     *
     * @var array<FoodsaverForAvatar> Array of chat members, excluding the searching user
     *
     * @OA\Property(
     *     type="array",
     *     @OA\Items(ref=@Model(type=FoodsaverForAvatar::class))
     * )
     */
    public array $members;

    /**
     * Number of members in the chat.
     *
     * @OA\Property(example=12)
     */
    public int $member_count;

    public static function createFromArray(array $data): ChatSearchResult
    {
        $result = new ChatSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->last_message_date = $data['last_message_date'];
        $result->last_foodsaver_id = $data['last_foodsaver_id'];
        $result->last_foodsaver_name = $data['last_foodsaver_name'];
        $result->last_message = $data['last_message'];
        $result->member_count = $data['member_count'];
        $result->members = self::formatUserList($data, 'member');
        $result->setSearchString($data);

        return $result;
    }
}
