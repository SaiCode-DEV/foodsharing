<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Search\DTO;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class ChatSearchResult extends SearchResult
{
    public ?DateTime $lastMessageSentAt = null;

    #[OA\Property(example: 51, description: 'Unique identifier of the foodsaver who last sent a message in the chat.')]
    public ?int $lastFoodsaverId = null;

    #[OA\Property(example: 'Max', description: 'Name of the foodsaver who last sent a message in the chat.')]
    public ?string $lastFoodsaverName = null;

    #[OA\Property(example: 'Have a nice day!', description: 'Last message that was sent in the chat.')]
    public ?string $lastMessage = null;

    #[OA\Property(description: 'Members of the chat. This includes at most 5 members to be displayed in the search result.',
        type: 'array', items: new OA\Items(ref: new Model(type: Profile::class))
    )]
    public array $members;

    #[OA\Property(example: 12, description: 'Number of members in the chat.')]
    public int $memberCount;

    public static function createFromArray(array $data): ChatSearchResult
    {
        $result = new ChatSearchResult();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->lastMessageSentAt = Carbon::parse($data['last_message_date']);
        $result->lastFoodsaverId = $data['last_foodsaver_id'];
        $result->lastFoodsaverName = $data['last_foodsaver_name'];
        $result->lastMessage = $data['last_message'];
        $result->memberCount = $data['member_count'];
        $result->members = self::formatUserList($data, 'member');
        $result->setSearchString($data);

        return $result;
    }
}
