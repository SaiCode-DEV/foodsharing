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

    /** @var array<string, Profile[]> */
    public array $reactions = [];

    // TODO change to create method
    public static function createFromArray(array $data): ForumPost
    {
        $result = new self();
        $result->id = $data['id'];
        $result->body = $data['body'];
        $result->createdAt = new DateTime($data['time']);
        $result->author = new Profile($data, 'author_');
        $result->hidden = HiddenPostInfo::tryCreateFromArray($data);

        return $result;
    }
}
