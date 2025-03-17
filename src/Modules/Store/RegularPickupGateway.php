<?php

namespace Foodsharing\Modules\Store;

use DateTime;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Store\DTO\RegularPickup;

class RegularPickupGateway extends BaseGateway
{
    public function __construct(
        Database $db,
    ) {
        parent::__construct($db);
    }

    /**
     * Return a list for regular pickups for a store.
     *
     * @return RegularPickup[] List of found regular pickups for the store
     */
    public function getRegularPickup(int $storeId): array
    {
        $times = $this->db->fetchAll('
			SELECT `time`, `dow`, `fetcher`, `description`
			FROM `fs_abholzeiten`
			WHERE `betrieb_id` = :storeId
			ORDER BY dow, time
		', [':storeId' => $storeId]);

        return array_map(fn ($row) => RegularPickup::createFromArray($row), $times);
    }

    public function insertOrUpdateRegularPickup(int $storeId, RegularPickup $regularPickup): int
    {
        $column_values = [];
        $column_values['time'] = $regularPickup->startTimeOfPickup;
        $column_values['dow'] = $regularPickup->weekday;
        $column_values['fetcher'] = $regularPickup->maxCountOfSlots;
        $column_values['betrieb_id'] = $storeId;
        $column_values['description'] = $regularPickup->description;

        return $this->db->insertOrUpdate('fs_abholzeiten', $column_values);
    }

    private function calculateBeginOfWeek(DateTime $timestamp): DateTime
    {
        $dateTimeWithFirstTimeOfWeek = (new DateTime($timestamp->format('Y-m-d 00:00:00')));
        $offsetToStartDayOfWeek = intval($timestamp->format('w'));

        return $dateTimeWithFirstTimeOfWeek->sub(\DateInterval::createFromDateString($offsetToStartDayOfWeek . ' days'));
    }

    private function convertDateTimeToTotalSec(DateTime $timestamp): int
    {
        $measureBegin = $this->calculateBeginOfWeek($timestamp);

        return $timestamp->getTimestamp() - $measureBegin->getTimestamp();
    }

    /**
     * Returns a list of regular pickups for a store depending on the date range which is provided.
     *
     * This allows to find all regular pickups like for the next two days.
     *
     * @param int $storeId Identifier of the store to check
     * @param DateTime $from Start datetime for search
     * @param DateTime $lastDay End date time which should be included in search
     *
     * @return RegularPickup[] List of regular pickups
     */
    public function getRegularPickupsForRange(int $storeId, DateTime $from, DateTime $lastDay): array
    {
        assert($lastDay->getTimestamp() <= $from->getTimestamp());

        $startTimeInSec = $this->convertDateTimeToTotalSec($from);
        $lastTimeInSec = $this->convertDateTimeToTotalSec($lastDay);

        $dateTimeBeginOfWeek = $this->calculateBeginOfWeek($from);
        $secBetweenStartAndEnd = $lastDay->getTimestamp() - $dateTimeBeginOfWeek->getTimestamp();

        $diffInWeeks = $secBetweenStartAndEnd / 604800;
        $weekOverRun = $diffInWeeks >= 1;

        //  start <= dbDateTime <= end
        $timeConstrain = '(? <= TIME_TO_SEC(`time`)+`dow`*86400 AND TIME_TO_SEC(`time`)+`dow`*86400 <= ?)';
        if ($weekOverRun) {
            // (start <= dbDateTime <= wochenEnde) or (dbDateTime <= end)
            $timeConstrain = '(? <= TIME_TO_SEC(`time`)+`dow`*86400 AND TIME_TO_SEC(`time`)+`dow`*86400 < 604800) OR (TIME_TO_SEC(`time`)+`dow`*86400 <= ?)';
        }

        $query = 'SELECT `time`, `dow`, `fetcher`, `description` FROM fs_abholzeiten WHERE `betrieb_id` = ? AND (' . $timeConstrain . ')';

        $results = $this->db->fetchAll($query, [$storeId, $startTimeInSec, $lastTimeInSec]);

        return array_map(fn ($row) => RegularPickup::createFromArray($row), $results);
    }

    public function deleteAllRegularPickups($storeId)
    {
        return $this->db->delete('fs_abholzeiten', ['betrieb_id' => $storeId]);
    }

    public function getRegularPickupTimesForStoresOfUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT
                pickups.`betrieb_id`, pickups.`dow`, pickups.`time`, pickups.`fetcher`, pickups.`description`
            FROM fs_betrieb_team team
            JOIN fs_abholzeiten pickups ON pickups.`betrieb_id` = team.`betrieb_id`
            WHERE team.`foodsaver_id` = :userId
            AND team.`active` = :activeStatus',
            [
                ':userId' => $userId,
                ':activeStatus' => MembershipStatus::MEMBER,
            ]);
    }
}
