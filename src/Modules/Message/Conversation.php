<?php

namespace Foodsharing\Modules\Message;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class Conversation
{
    public int $id = 0;
    public ?string $title = null;
    public ?int $storeId = null;
    public int $unreadMessages = 0;
    public array $members = [];
    public ?Message $lastMessage = null;

    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Message::class)))]
    public ?array $messages = null;
}
