<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use DateTimeZone;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellUpdaterInterface;
use Foodsharing\Modules\Bell\BellUpdateTrigger;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\DTO\PickupAgendaEntry;
use Foodsharing\Modules\Store\DTO\OneTimePickup;
use Foodsharing\Modules\Store\DTO\PickupSignUp;

class PickupGateway extends BaseGateway implements BellUpdaterInterface
{
    private readonly BellGateway $bellGateway;
    private readonly RegularPickupGateway $regularPickupGateway;

    public function __construct(
        Database $db,
        BellGateway $bellGateway,
        BellUpdateTrigger $bellUpdateTrigger,
        RegularPickupGateway $regularPickupGateway
    ) {
        parent::__construct($db);

        $this->bellGateway = $bellGateway;
        $this->regularPickupGateway = $regularPickupGateway;

        $bellUpdateTrigger->subscribe($this);
    }

    public function addFetcher(int $fsId, int $storeId, DateTime $date, bool $confirmed = false): void
    {
        $this->db->insertIgnore('fs_abholer', [
            'foodsaver_id' => $fsId,
            'betrieb_id' => $storeId,
            'date' => $this->db->date($date),
            'confirmed' => $confirmed,
        ]);

        if (!$confirmed) {
            $this->updateBellNotificationForStoreManagers($storeId, true);
        }
    }

    /**
     * @param ?int $storeId if set, only remove pickup dates for the user in this store
     */
    public function deleteAllDatesFromAFoodsaver(int $userId, ?int $storeId = null)
    {
        $criteria = [
            'foodsaver_id' => $userId,
            'date >' => $this->db->now(),
        ];
        if (!is_null($storeId)) {
            $criteria['betrieb_id'] = $storeId;
        }

        $affectedStoreIds = $this->db->fetchAllValuesByCriteria('fs_abholer', 'betrieb_id', $criteria);
        $result = $this->db->delete('fs_abholer', $criteria);

        foreach ($affectedStoreIds as $storeIdDel) {
            $this->updateBellNotificationForStoreManagers($storeIdDel);
        }

        return $result;
    }

    public function removeFetcher(int $fsId, int $storeId, DateTime $date)
    {
        $deletedRows = $this->db->delete('fs_abholer', [
            'foodsaver_id' => $fsId,
            'betrieb_id' => $storeId,
            'date' => $this->db->date($date),
        ]);
        $this->updateBellNotificationForStoreManagers($storeId);

        return $deletedRows;
    }

    /**
     * @return PickupAgendaEntry[]
     */
    public function getSameDayPickupsForUser(int $fsId, DateTime $day): array
    {
        $pickups = $this->db->fetchAll('
			SELECT 	p.`date`,
					p.confirmed AS isConfirmed,
					s.name AS storeName,
					s.id AS storeId

			FROM            `fs_abholer` p
			LEFT OUTER JOIN `fs_betrieb` s  ON  s.id = p.betrieb_id

			WHERE    p.foodsaver_id = :fsId
			AND      DATE(p.`date`) = DATE(:pickupDay)

			ORDER BY p.`date`
		', [
            ':fsId' => $fsId,
            ':pickupDay' => $this->db->date($day, false),
        ]);

        return array_map(fn ($pickup) => PickupAgendaEntry::create(
            $pickup['storeId'],
            $pickup['storeName'],
            new Carbon($pickup['date']),
            boolval($pickup['isConfirmed']),
        ), $pickups);
    }

    /**
     * @param int $fsId foodsaver ID
     * @param Carbon $from Date From
     * @param Carbon $to Date To
     *
     * This function counts how many pickups the user has in stores that
     * have set to use the region pickup rule. It considers from a certain pickupdate
     * into the future and into the past. It has on purpose no region restriction for the stores the user is in.
     */
    public function getNumberOfPickupsForUserWithStoreRules(int $fsId, Carbon $from, Carbon $to): int
    {
        $result = $this->db->fetchAll('
			SELECT 	count(*) as Anzahl
			FROM            `fs_abholer` p
			LEFT OUTER JOIN `fs_betrieb` s  ON  s.id = p.betrieb_id
			WHERE    p.foodsaver_id = :fsId
			AND      DATE(p.date) BETWEEN DATE(:from) and DATE(:to)
			and 	 s.use_region_pickup_rule = 1
		', [
            ':fsId' => $fsId,
            ':from' => $this->db->date($from, false),
            ':to' => $this->db->date($to, false),
        ]);

        return $result[0]['Anzahl'];
    }

    /**
     * @param int $fsId foodsaver ID
     * @param Carbon $pickup Date of the pickup
     *
     * This function counts how many pickups the user has in stores that
     * have set to use the region pickup rule for the same day. It considers from a certain pickupdate
     * into the future and into the past. It has on purpose no region restriction for the stores the user is in.
     */
    public function getNumberOfPickupsForUserWithStoreRulesSameDay(int $fsId, Carbon $pickup): int
    {
        $result = $this->db->fetchAll('
			SELECT 	count(*) as Anzahl
			FROM            `fs_abholer` p
			LEFT OUTER JOIN `fs_betrieb` s  ON  s.id = p.betrieb_id
			WHERE    p.foodsaver_id = :fsId
			AND      DATE(p.date) = DATE(:pickup)
			and 	 s.use_region_pickup_rule = 1
		', [
            ':fsId' => $fsId,
            ':pickup' => $this->db->date($pickup, false),
        ]);

        return $result[0]['Anzahl'];
    }

    /**
     * @param bool $markNotificationAsUnread if an older notification exists that has already been marked as read,
     * it can be marked as unread again while updating it
     */
    public function updateBellNotificationForStoreManagers(int $storeId, bool $markNotificationAsUnread = false): void
    {
        $storeName = $this->getStoreName($storeId);
        $messageIdentifier = BellType::createIdentifier(BellType::STORE_UNCONFIRMED_PICKUP, $storeId);
        $messageCount = $this->getUnconfirmedFetchesCount($storeId);
        $messageVars = ['betrieb' => $storeName, 'count' => $messageCount];
        $messageTimestamp = $this->getNextUnconfirmedFetchTime($storeId);
        $messageExpiration = $messageTimestamp;

        $oldBellExists = $this->bellGateway->bellWithIdentifierExists($messageIdentifier);

        if ($messageCount === 0 && $oldBellExists) {
            $this->bellGateway->delBellsByIdentifier($messageIdentifier);
        } elseif ($messageCount > 0 && $oldBellExists) {
            $oldBellId = $this->bellGateway->getOneByIdentifier($messageIdentifier);
            $data = [
                'vars' => $messageVars,
                'time' => $messageTimestamp,
                'expiration' => $messageExpiration,
            ];
            $this->bellGateway->updateBell($oldBellId, $data, $markNotificationAsUnread);
        } elseif ($messageCount > 0 && !$oldBellExists) {
            $bellData = Bell::create(
                'betrieb_fetch_title',
                'betrieb_fetch',
                'fas fa-user-clock',
                ['href' => '/?page=fsbetrieb&id=' . $storeId],
                $messageVars,
                $messageIdentifier,
                false,
                $messageExpiration,
                $messageTimestamp
            );
            $this->bellGateway->addBell($this->getResponsibleFoodsaverIds($storeId), $bellData);
        }
    }

    public function updateExpiredBells(): void
    {
        $expiredBells = $this->bellGateway->getExpiredByIdentifier(str_replace('%d', '%', (string)BellType::STORE_UNCONFIRMED_PICKUP));

        foreach ($expiredBells as $bell) {
            $storeId = substr($bell->identifier, strrpos($bell->identifier, '-') + 1);
            $this->updateBellNotificationForStoreManagers(intval($storeId));
        }
    }

    public function confirmFetcher(int $fsid, int $storeId, DateTime $date): int
    {
        $result = $this->db->update(
            'fs_abholer',
            ['confirmed' => 1],
            ['foodsaver_id' => $fsid, 'betrieb_id' => $storeId, 'date' => $this->db->date($date)]
        );

        $this->updateBellNotificationForStoreManagers($storeId);

        return $result;
    }

    /**
     * Returns a list of pickup sign up for the store on the date.
     *
     * @return PickupSignUp[]
     */
    public function getPickupSignUpsForDate(int $storeId, DateTime $date): array
    {
        return $this->getPickupSignUpsForDateRange($storeId, $date, $date);
    }

    /**
     * Returns a list of all sign ups for pickups of the store in the date range.
     *
     * @param $storeId Store of interest
     * @param $from Start date for search of sign ups
     * @param $to Last date which should be found for search of sign ups
     *
     * @return PickupSignUp[] List of found signups
     */
    private function getPickupSignUpsForDateRange(int $storeId, DateTime $from, ?DateTime $to = null): array
    {
        $condition = ['date >=' => $this->db->date($from), 'betrieb_id' => $storeId];
        if (!is_null($to)) {
            $condition['date <='] = $this->db->date($to);
        }
        $result = $this->db->fetchAllByCriteria(
            'fs_abholer',
            ['foodsaver_id', 'date', 'confirmed'],
            $condition
        );

        return array_map(fn ($e) => PickupSignUp::create(
            DateTime::createFromFormat('Y-m-d H:i:s', $e['date'], new DateTimeZone('Europe/Berlin')),
            $e['foodsaver_id'],
            boolval($e['confirmed']),
        ), $result);
    }

    public function getPickupHistory(int $storeId, DateTime $from, DateTime $to): array
    {
        return $this->db->fetchAll('
			SELECT	a.foodsaver_id AS foodsaverId,
					a.confirmed,
					a.date,
					UNIX_TIMESTAMP(a.date) AS date_ts,
                    f.description

			FROM	fs_abholer a
            LEFT OUTER JOIN fs_fetchdate f ON
                f.betrieb_id = a.betrieb_id AND f.time = a.date

			WHERE	a.betrieb_id = :storeId
			AND     a.date >= :from
			AND     a.date <= :to

			ORDER BY a.date
		', [
            ':storeId' => $storeId,
            ':from' => $this->db->date($from),
            ':to' => $this->db->date($to),
        ]);
    }

    /**
     * Returns a list of created one time pickups (no regular pickups) of the day.
     *
     * @param int $storeId Identifier of the store to check
     * @param DateTime $date Datetime for search
     *
     * @return OneTimePickup[] List of pickups
     */
    public function getOnetimePickups(int $storeId, DateTime $date)
    {
        return $this->getOnetimePickupsForRange($storeId, $date, $date);
    }

    /**
     * Returns a list of created one time pickups (no regular pickups) of a date range.
     *
     * @param int $storeId Identifier of the store to check
     * @param DateTime $from Start datetime for search
     * @param DateTime $to End date time
     *
     * @return OneTimePickup[] List of pickups
     */
    private function getOnetimePickupsForRange(int $storeId, DateTime $from, ?DateTime $to): array
    {
        $condition = [
            'betrieb_id' => $storeId,
            'time >=' => $this->db->date($from),
        ];
        if ($to) {
            $condition = array_merge($condition, ['time <=' => $this->db->date($to)]);
        }
        $result = $this->db->fetchAllByCriteria('fs_fetchdate', ['time', 'fetchercount', 'description'], $condition);

        return array_map(fn (array $dbItem): OneTimePickup => OneTimePickup::create(
            DateTime::createFromFormat('Y-m-d H:i:s', $dbItem['time'], new DateTimeZone('Europe/Berlin')),
            $dbItem['fetchercount'],
            $dbItem['description']
        ), $result);
    }

    public function addOnetimePickup(int $storeId, OneTimePickup $pickup)
    {
        $this->db->insert('fs_fetchdate', [
            'betrieb_id' => $storeId,
            'time' => $this->db->date($pickup->date),
            'fetchercount' => $pickup->slots,
            'description' => $pickup->description,
        ]);
    }

    public function updateOnetimePickupTotalSlots(int $storeId, OneTimePickup $pickup): bool
    {
        return $this->db->update(
            'fs_fetchdate',
            ['fetchercount' => $pickup->slots, 'description' => $pickup->description],
            ['betrieb_id' => $storeId, 'time' => $this->db->date($pickup->date)]
        ) === 1;
    }

    private function getFutureRegularPickupInterval(int $storeId): CarbonInterval
    {
        $result = $this->db->fetchValueByCriteria('fs_betrieb', 'prefetchtime', ['id' => $storeId]);

        return CarbonInterval::seconds($result);
    }

    private function getNextUnconfirmedFetchTime(int $storeId): DateTime
    {
        $date = $this->db->fetchValue('
			SELECT  MIN(`date`)

			FROM    `fs_abholer`

			WHERE   `betrieb_id` = :storeId
			AND     `confirmed` = 0
			AND     `date` > :date
		', [
            ':storeId' => $storeId,
            ':date' => $this->db->now(),
        ]);

        return new DateTime($date);
    }

    private function getUnconfirmedFetchesCount(int $storeId)
    {
        return $this->db->count('fs_abholer', ['betrieb_id' => $storeId, 'confirmed' => 0, 'date >' => $this->db->now()]);
    }

    /**
     * @param Carbon $from DateRange start for all slots. Now if empty.
     * @param Carbon $to DateRange for regular slots - future pickup interval if empty
     * @param Carbon $oneTimeSlotTo DateRange for onetime slots to be taken into account
     */
    public function getPickupSlots(int $storeId, ?Carbon $from = null, ?Carbon $to = null, ?Carbon $oneTimeSlotTo = null): array
    {
        $intervalFuturePickupSignup = $this->getFutureRegularPickupInterval($storeId);
        $from ??= Carbon::now();
        $extendedToDate = Carbon::now('Europe/Berlin')->add($intervalFuturePickupSignup);
        $to ??= $extendedToDate;
        $regularSlots = $this->regularPickupGateway->getRegularPickup($storeId);
        $onetimeSlots = $this->getOnetimePickupsForRange($storeId, $from, $oneTimeSlotTo);
        $signupsTo = is_null($oneTimeSlotTo) ? null : max($to, $oneTimeSlotTo);
        $signups = $this->getPickupSignUpsForDateRange($storeId, $from, $signupsTo);

        if ($intervalFuturePickupSignup->isEmpty()) {
            // No regular pickups. We have to manually set regularSlots to an
            // empty array to avoid phantom pickups being reported. This can
            // happen when the prefechtetime is 0, no slot existed at this date
            // (e.g. holiday) and this slot wasn't explicitly deleted.
            $regularSlots = [];
        }

        $slots = [];
        foreach ($regularSlots as $slot) {
            $date = $from->copy();
            $date->addDays($this->realMod($slot->weekday - $date->format('w'), 7));
            $date->setTimeFromTimeString($slot->startTimeOfPickup)->shiftTimezone('Europe/Berlin');
            if ($date < $from) {
                /* setting time could shift it into past */
                $date->addDays(7);
            }
            while ($date <= $to) {
                if (empty(array_filter($onetimeSlots, fn ($e) => $date == $e->date))) {
                    /* only take this regular slot into account when there is no manual slot for the same time */
                    $occupiedSlots = array_map(
                        fn ($e) => ['foodsaverId' => $e->foodsaverId, 'isConfirmed' => $e->isConfirmed],
                        array_filter(
                            $signups,
                            fn ($e) => $date == $e->date
                        )
                    );
                    $isAvailable =
                        $date > Carbon::now() &&
                        $date <= $extendedToDate &&
                        $slot->maxCountOfSlots > count($occupiedSlots);
                    $slots[] = [
                        'date' => $date,
                        'totalSlots' => $slot->maxCountOfSlots,
                        'occupiedSlots' => array_values($occupiedSlots),
                        'isAvailable' => $isAvailable,
                        'description' => $slot->description,
                    ];
                }

                $date = $date->copy()->addDays(7);
            }
        }
        foreach ($onetimeSlots as $slot) {
            $occupiedSlots = array_map(
                fn ($e) => ['foodsaverId' => $e->foodsaverId, 'isConfirmed' => $e->isConfirmed],
                array_filter(
                    $signups,
                    fn ($e) => $slot->date == $e->date
                )
            );
            if ($slot->slots === 0 && count($occupiedSlots) === 0) {
                /* Do not display empty/cancelled pickups.
                Do show them, when somebody is signed up (although this should not happen) */
                continue;
            }
            /* Onetime slots are always in the future available for signups */
            $isInFuture = $slot->date > Carbon::now();
            $hasFree = $slot->slots > count($occupiedSlots);

            $slots[] = [
                'date' => new Carbon($slot->date),
                'totalSlots' => $slot->slots,
                'occupiedSlots' => array_values($occupiedSlots),
                'isAvailable' => $isInFuture && $hasFree,
                'description' => $slot->description,
            ];
        }

        return $slots;
    }

    /**
     * Returns past pickup dates to which the foodsaver signed in.
     * If either page or pageSize is set to -1 pagination is disabled and all entries are returned.
     *
     * @param int $fsId ID of the foodsaver
     * @param bool $fullHistory whether to include entries older than a month
     *
     * @return array the fetched pickups including information about the other fs who took part and the store
     */
    public function getPastPickups(int $fsId, Pagination $pagination, bool $fullHistory): array
    {
        $timeContraint = '';
        if (!$fullHistory) {
            $timeContraint = 'AND p1.date > NOW() - INTERVAL 1 MONTH ';
        }
        $query = 'SELECT
				s.id AS store_id,
				s.name AS store_name,
				UNIX_TIMESTAMP(p1.`date`) AS `timestamp`,
				p1.confirmed,
				GROUP_CONCAT(f.id) AS fs_ids,
				GROUP_CONCAT(QUOTE(CONCAT(f.name, " ", f.nachname))) AS fs_names,
				GROUP_CONCAT(IFNULL(f.photo, "")) AS fs_avatars,
				GROUP_CONCAT(p2.confirmed) AS slot_confimations,
                d.description
			FROM fs_abholer p1
			LEFT JOIN fs_abholer p2 ON p1.betrieb_id = p2.betrieb_id AND p1.date = p2.date
			LEFT JOIN fs_foodsaver f ON f.id = p2.foodsaver_id
			LEFT JOIN fs_betrieb s ON s.id = p1.betrieb_id
            LEFT OUTER JOIN fs_fetchdate d ON d.betrieb_id = p1.betrieb_id AND d.time = p1.date
			WHERE p1.foodsaver_id = :fs_id AND p1.date < NOW() '
            . $timeContraint .
            'GROUP BY p1.betrieb_id, p1.date
			ORDER BY p1.date DESC'
            . $this->buildPaginationSqlLimit($pagination);
        $params = $this->addPaginationSqlLimitParameters($pagination, ['fs_id' => $fsId]);

        return $this->db->fetchAll($query, $params);
    }

    /**
     * Returns the next dates which the foodsaver signed into.
     *
     * @param int $fsId ID of the foodsaver
     * @param int|null $limit if not null, the result will be limited to a number of dates
     *
     * @throws \Exception
     */
    public function getNextPickups(int $fsId, int $limit = null, int $runningPickupsBufferInMinutes = 0): array
    {
        // TODO refactor to return PickupForListView DTOs
        $stm = 'SELECT
				s.id AS store_id,
				s.name AS store_name,
				CONCAT(s.str, ", ", s.plz, " ", s.stadt) AS `address`,
				UNIX_TIMESTAMP(a.`date`) AS `timestamp`,
				a.confirmed,
				GROUP_CONCAT(f.id) AS fs_ids,
				GROUP_CONCAT(QUOTE(CONCAT(f.name, " ", f.nachname))) AS fs_names,
				GROUP_CONCAT(IFNULL(f.photo, "")) AS fs_avatars,
				GROUP_CONCAT(a2.confirmed) AS slot_confimations,
				d.fetchercount AS max_fetchers,
                d.`description` AS `description`
			FROM `fs_abholer` a
			LEFT OUTER JOIN `fs_abholer` a2 ON
				a.betrieb_id = a2.betrieb_id AND a.date = a2.date
			LEFT OUTER JOIN `fs_foodsaver` f ON
				a2.foodsaver_id = f.id
			LEFT OUTER JOIN `fs_betrieb` s ON
				a.betrieb_id = s.id
			LEFT OUTER JOIN `fs_fetchdate` d ON
				a.betrieb_id = d.betrieb_id AND a.`date` = d.time
			WHERE a.foodsaver_id = :fs_id AND a.`date` > DATE_SUB(NOW(), INTERVAL :buffer MINUTE)
			GROUP BY a.id
			ORDER BY a.`date`';

        $params = [
            ':fs_id' => $fsId,
            ':buffer' => $runningPickupsBufferInMinutes
        ];

        if (!is_null($limit)) {
            $stm .= ' LIMIT :limit';
            $params[':limit'] = $limit;
        }

        return $this->db->fetchAll($stm, $params);
    }

    public function getFuturePickupTimesForStoresOfUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT
                pickup.`betrieb_id`, pickup.`time`, pickup.`fetchercount`, pickup.`description`
            FROM fs_betrieb_team team
            JOIN fs_fetchdate pickup ON pickup.`betrieb_id` = team.`betrieb_id`
            WHERE team.`foodsaver_id` = :userId
            AND team.`active` = :activeStatus
            AND pickup.`time` > NOW()',
            [
                ':userId' => $userId,
                ':activeStatus' => MembershipStatus::MEMBER,
            ]);
    }

    public function getFutureFetchersForStoresOfUser(int $userId): array
    {
        return $this->db->fetchAll('SELECT
                fetcher.`betrieb_id`, fetcher.`date`, fetcher.`confirmed`, foodsaver.`id`, foodsaver.`name`, foodsaver.`photo`
            FROM fs_betrieb_team team
            JOIN fs_abholer fetcher ON fetcher.`betrieb_id` = team.`betrieb_id`
            JOIN fs_foodsaver foodsaver ON fetcher.`foodsaver_id` = foodsaver.`id`
            WHERE team.`foodsaver_id` =  :userId
            AND team.`active` = :activeStatus
            AND fetcher.`date` > NOW()',
            [
                ':userId' => $userId,
                ':activeStatus' => MembershipStatus::MEMBER,
            ]);
    }

    private function realMod(int $a, int $b)
    {
        $res = $a % $b;
        if ($res < 0) {
            return $res += abs($b);
        }

        return $res;
    }

    private function getStoreName(int $storeId): string
    {
        return $this->db->fetchValueByCriteria('fs_betrieb', 'name', ['id' => $storeId]);
    }

    /**
     * @return int[]
     */
    private function getResponsibleFoodsaverIds(int $storeId): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_betrieb_team', 'foodsaver_id', [
            'betrieb_id' => $storeId,
            'verantwortlich' => 1
        ]);
    }
}
