<?php

namespace Foodsharing\Utility;

use Carbon\Carbon;
use DateTime;

final readonly class TimeHelper
{
    public function __construct()
    {
    }

    /**
     * Returns the number of days by which the date is in the future, or 0 if the date is in the past.
     */
    public function daysInFuture(DateTime $date): int
    {
        if (Carbon::instance($date)->isPast()) {
            return 0;
        }

        return (int)Carbon::today()->diffInDays($date, true);
    }
}
