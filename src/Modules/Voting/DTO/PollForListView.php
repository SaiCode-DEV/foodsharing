<?php

namespace Foodsharing\Modules\Voting\DTO;

use Carbon\Carbon;
use DateTime;

/**
 * Class that represents a poll as it is used in the poll list view.
 */
class PollForListView
{
    public int $id;
    public string $name;
    public DateTime $startDate;
    public DateTime $endDate;
    public bool $isEligible;
    public bool $hasVoted;

    public static function createFromArray(array $data): PollForListView
    {
        $poll = new PollForListView();
        $poll->id = $data['id'];
        $poll->name = $data['name'];
        $poll->startDate = Carbon::parse($data['start']);
        $poll->endDate = Carbon::parse($data['end']);
        $poll->isEligible = $data['isEligible'];
        $poll->hasVoted = $data['hasVoted'];

        return $poll;
    }
}
