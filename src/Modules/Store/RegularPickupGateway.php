<?php

namespace Foodsharing\Modules\Store;

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
