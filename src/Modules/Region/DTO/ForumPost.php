<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class ForumPost
{
    public int $id;
    public ?string $body;
    public DateTime $createdAt;
    public Profile $author;
    public ?HiddenPostInfo $hidden;

    public ?DateTime $lastEditedAt = null;

    /** @var array<string, Profile[]> */
    public array $reactions = [];

    // TODO change to create method
    public static function createFromArray(array $data): ForumPost
    {
        $result = new self();
        $result->id = $data['id'];
        $result->body = $data['body'];
        $result->createdAt = new DateTime($data['time']);
        $result->author = new Profile($data['author_id'], $data['author_name'], $data['author_photo'], (bool)$data['author_is_sleeping']);
        $result->hidden = HiddenPostInfo::tryCreateFromArray($data);
        $result->lastEditedAt = empty($data['last_edited_at']) ? null : new DateTime($data['last_edited_at']);

        return $result;
    }
}
