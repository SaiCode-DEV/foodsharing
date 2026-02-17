<?php

namespace Foodsharing\Modules\Store\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class StoreLogEntry
{
    public function __construct(
        public DateTime $performedAt,
        public int $actionType,
        public Profile $actor,
        public ?Profile $target,
        public ?DateTime $dateReference,
        public ?string $content,
        public ?string $reason,
    ) {
    }
}
