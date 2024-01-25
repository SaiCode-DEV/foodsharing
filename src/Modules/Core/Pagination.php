<?php

namespace Foodsharing\Modules\Core;

use Symfony\Component\Validator\Constraints as Assert;

class Pagination
{
    /**
     * Count of item per page.
     */
    #[Assert\Positive]
    public ?int $pageSize = null;

    /**
     * Offset to start.
     */
    #[Assert\Positive]
    public int $offset = 0;
}
