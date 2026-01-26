<?php

namespace Foodsharing\Utility;

use Carbon\Carbon;
use DateTime;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class TimeHelper
{
    public function __construct()
    {
    }

    public static function parsePickupDate(string $pickupDate): Carbon
    {
        try {
            $date = new Carbon($pickupDate);
            $date->setTimezone('Europe/Berlin');

            return $date;
        } catch (Exception) {
            throw new BadRequestHttpException('Invalid date format');
        }
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
