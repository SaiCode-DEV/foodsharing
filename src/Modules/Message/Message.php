<?php

namespace Foodsharing\Modules\Message;

use Carbon\Carbon;
use DateTime;

class Message
{
    public int $id;
    public string $body;
    public DateTime $sentAt;
    public int $authorId;

    public function __construct(string $body, int $authorId, Carbon $sentAt, int $messageId)
    {
        $this->authorId = $authorId;
        $this->sentAt = $sentAt;
        $this->body = $body;
        $this->id = $messageId;
    }
}
