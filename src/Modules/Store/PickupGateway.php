<?php

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTime;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\BellUpdaterInterface;
use Foodsharing\Modules\Bell\BellUpdateTrigger;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
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

    public function addFetcher(int $fsId, int $storeId, DateTime $date, bool $confirmed = false): int
    {
        $result = $this->db->insertIgnore('fs_abholer', [
            'foodsaver_id' => $fsId,
            'betrieb_id' => $storeId,
            'date' => $this->db->date($date),
            'confirmed' => $confirmed,
        ]);

        if (!$confirmed) {
            $this->updateBellNotificationForStoreManagers($storeId, true);
        }

        return $result;
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

    public function getSameDayPickupsForUser(int $fsId, DateTime $day): array
    {
        return $this->db->fetchAll('
			SELECT 	p.`date`,
					p.confirmed AS isConfirmed,
					s.name AS storeName,
					s.id AS storeId

			FROM            `fs_abholer` p
			LEFT OUTER JOIN `fs_betrieb` s  ON  s.id = p.betrieb_id

			WHERE    p.foodsaver_id = :fsId
			AND      DATE(p.`date`) = DATE(:pickupDay)
			AND      p.`date` >= :now

			ORDER BY p.`date`
		', [
            ':fsId' => $fsId,
            ':pickupDay' => $this->db->date($day, false),
            ':now' => $this->db->now(),
        ]);
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
    public function getPickupSignUpsForDateRange(int $storeId, DateTime $from, ?DateTime $to = null)
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

        return array_map(fn ($e) => PickupSignUp::createFromArray($e), $result);
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
    public function getOnetimePickupsForRange(int $storeId, DateTime $from, ?DateTime $to)
    {
        $condition = [
            'betrieb_id' => $storeId,
            'time >=' => $this->db->date($from),
        ];
        if ($to) {
            $condition = array_merge($condition, ['time <=' => $this->db->date($to)]);
        }
        $result = $this->db->fetchAllByCriteria('fs_fetchdate', ['time', 'fetchercount', 'description'], $condition);

        return array_map(fn (array $dbItem): OneTimePickup => OneTimePickup::createFromArray($dbItem), $result);
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
     * @param int $page the number of the page to be queried (for pagination)
     * @param int $pageSize the size of pages to be queried (for pagination)
     * @param bool $fullHistory whether to include entries older than a month
     *
     * @return array the fetched pickups including information about the other fs who took part and the store
     */
    public function getPastPickups(int $fsId, int $page, int $pageSize, bool $fullHistory): array
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
			ORDER BY p1.date DESC';

        $params = ['fs_id' => $fsId];
        if ($page != -1 && $pageSize != -1) {
            $query .= ' LIMIT :page_size OFFSET :start_item_index';
            $params['start_item_index'] = $page * $pageSize;
            $params['page_size'] = $pageSize;
        }

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
    public function getNextPickups(int $fsId, int $limit = null): array
    {
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
			WHERE a.foodsaver_id = :fs_id AND a.`date` > NOW()
			GROUP BY a.id
			ORDER BY a.`date`';

        $params = [':fs_id' => $fsId];

        if (!is_null($limit)) {
            $stm .= ' LIMIT :limit';
            $params[':limit'] = $limit;
        }

        return $this->db->fetchAll($stm, $params);
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
