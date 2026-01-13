<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use OpenApi\Attributes as OA;

class ForumThreadPermissions
{
    public bool $mayModerate;

    public bool $mayHidePosts;

    #[OA\Property(description: 'Whether the user is allowed to delete posts of other users (own posts are always deletable)')]
    public bool $mayDelete;
}
