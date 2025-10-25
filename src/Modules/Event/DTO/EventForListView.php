<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Event\DTO;

use DateTime;

class EventForListView
{
    public int $id;
    public string $name;
    public DateTime $startDate;
    public DateTime $endDate;

    public static function create(int $id, string $name, DateTime $startDate, DateTime $endDate)
    {
        $result = new self();
        $result->id = $id;
        $result->name = $name;
        $result->startDate = $startDate;
        $result->endDate = $endDate;

        return $result;
    }
}
