<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class ForumThread
{
    public int $id;
    public int $regionId;

    #[OA\Property(description: 'Subforum identifier. 1 is the ambassador forum, 0 the normal forum.')]
    public int $subforumId;

    #[OA\Property(example: 'Beta Testing: API refactoring')]
    public string $title;

    #[OA\Property(example: 3, description: 'Pinned level from -1 (end of list) to 10 (top of list)')]
    public int $pinnedLevel;

    public bool $isLocked;

    #[OA\Property(description: 'Whether the thread is active, or needs to be checked by a moderator first')]
    public bool $isActive;

    #[OA\Property(description: 'Id of the user who initially created the thread')]
    public int $creatorId;

    #[OA\Property(description: 'Id of the last post in this thread')]
    public int $lastPostId;

    public ?ForumThreadPermissions $permissions = null;

    public ?SubscriptionsStatus $subscriptionsStatus = null;

    /** @var ?ForumPost[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ForumPost::class)))]
    public ?array $posts = null;
}
