<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Region\DTO;

use DateTime;
use Foodsharing\Modules\Foodsaver\Profile;

class HiddenPostInfo
{
    public string $reason;
    public Profile $moderator;
    public DateTime $time;

    public static function tryCreateFromArray(array $data): ?HiddenPostInfo
    {
        if (empty($data['hidden_reason'])) {
            return null;
        }

        $result = new self();
        $result->reason = $data['hidden_reason'];
        $result->moderator = new Profile($data, 'moderator_');
        $result->time = new DateTime($data['hidden_time']);

        return $result;
    }
}
