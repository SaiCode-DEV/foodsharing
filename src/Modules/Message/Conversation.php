<?php

namespace Foodsharing\Modules\Message;

class Conversation
{
    public int $id = 0;
    public ?string $title = null;
    public ?int $storeId = null;
    public int $unreadMessages = 0;
    public array $members = [];
    public ?Message $lastMessage = null;
    public ?array $messages = null;
}
