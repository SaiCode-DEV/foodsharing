<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class ForumPostSummary
{
    public DateTime $createdAt;
    public Profile $author;
}
