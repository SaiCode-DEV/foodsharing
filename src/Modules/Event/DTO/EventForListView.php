<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Event\DTO;

use Carbon\Carbon;
use DateTime;

class EventForListView
{
    public int $id;
    public string $name;
    public DateTime $startDate;
    public DateTime $endDate;

    public static function createFromArray(array $data): EventForListView
    {
        $result = new self();
        $result->id = $data['id'];
        $result->name = $data['name'];
        $result->startDate = new Carbon($data['start']);
        $result->endDate = new Carbon($data['end']);

        return $result;
    }
}
