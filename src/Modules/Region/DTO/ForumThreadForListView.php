<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use OpenApi\Attributes as OA;

class ForumThreadForListView
{
    public int $id;

    #[OA\Property(example: 'Beta Testing: API refactoring')]
    public string $title;

    #[OA\Property(example: 3, description: 'Pinned level from -1 (end of list) to 10 (top of list)')]
    public int $pinnedLevel;

    public bool $isLocked;

    public ForumPostSummary $latestPost;
}
