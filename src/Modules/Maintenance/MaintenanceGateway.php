<?php

namespace Foodsharing\Modules\Maintenance;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Basket\Status;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\SleepStatus;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;

class MaintenanceGateway extends BaseGateway
{
    /**
     * Sets the status of all outdated baskets to {@link Status::DELETED_OTHER_REASON}.
     *
     * @return int the number of changed baskets
     */
    public function deactivateOldBaskets(): int
    {
        return $this->db->update(
            'fs_basket',
            ['status' => Status::DELETED_OTHER_REASON],
            ['status' => Status::REQUESTED_MESSAGE_READ, 'until <' => $this->db->now()]
        );
    }

    /**
     * Removes sleep status from users if it was active since yesterday and the user is not endless inactive.
     *
     * @return int the number of users that were changed
     */
    public function wakeupSleepingUsers(): int
    {
        return $this->db->update(
            'fs_foodsaver',
            ['sleep_status' => SleepStatus::NONE, 'sleep_from' => null, 'sleep_until' => null],
            ['sleep_until <' => $this->db->curdate(), 'sleep_status !=' => SleepStatus::FULL]
        );
    }

    /**
     * Sets sleep status if the sleep status is now and in the future active.
     *
     * @return int the number of users that were changed
     */
    public function putUsersToSleep(): int
    {
        return $this->db->update(
            'fs_foodsaver',
            ['sleep_status' => SleepStatus::TEMP],
            ['sleep_until >=' => $this->db->curdate()]
        );
    }

    /**
     * Returns the managers for all stores that have pickup free slots in the next two days.
     */
    public function getStoreManagersWhichWillBeAlerted(): array
    {
        $dow = (int)date('w');
        $dowTomorrow = ($dow + 1) % 7;

        // find all stores with pickup slots today or tomorrow
        $storesInRange = $this->db->fetchAll('
			SELECT
				DISTINCT z.betrieb_id
			FROM
				fs_abholzeiten z
			LEFT JOIN
				fs_betrieb b
			ON
				z.betrieb_id = b.id
			WHERE
				b.betrieb_status_id = :established
			AND
			(
				(
					z.dow = :dow
					AND
					z.time >= :time
				)
				OR
					z.dow = :dowTomorrow
			)
		', [
            ':dow' => $dow,
            ':time' => date('H:i:s'),
            ':dowTomorrow' => $dowTomorrow,
            ':established' => CooperationStatus::COOPERATION_ESTABLISHED->value,
        ]);

        if (!empty($storesInRange)) {
            $storeIds = [];
            foreach ($storesInRange as $store) {
                $storeIds[(int)$store['betrieb_id']] = (int)$store['betrieb_id'];
            }

            // remove all stores from the list that have someone who will pickup
            $storeWithFetcher = $this->db->fetchAll('
				SELECT
					DISTINCT a.betrieb_id AS id
				FROM
					fs_abholer a
				WHERE
					a.confirmed = 1
				AND
					a.betrieb_id IN(' . implode(',', $storeIds) . ')
				AND
					a.date >= NOW()
				AND
					a.date <= CURRENT_DATE() + INTERVAL 2 DAY
			');

            foreach ($storeWithFetcher as $s) {
                unset($storeIds[$s['id']]);
            }

            // return the managers for all remaining stores in the list
            if (!empty($storeIds)) {
                return $this->db->fetchAll('
					SELECT
						fs.id AS fs_id,
						fs.email AS fs_email,
						fs.geschlecht,
						fs.name AS fs_name,
						b.id AS betrieb_id,
						b.name AS betrieb_name

					FROM
						fs_betrieb b,
						fs_betrieb_team bt,
						fs_foodsaver fs

					WHERE
						b.id = bt.betrieb_id

					AND
						bt.foodsaver_id = fs.id

					AND
						bt.active = 1

					AND
						bt.verantwortlich = 1

					AND
						b.id IN(' . implode(',', $storeIds) . ')');
            }
        }

        return [];
    }

    /**
     * Deletes all outdated entries from the blocked IPs.
     *
     * @return int the number of deleted entries
     */
    public function deleteOldIpBlocks(): int
    {
        return $this->db->execute('DELETE FROM `fs_ipblock` WHERE UNIX_TIMESTAMP(NOW()) > UNIX_TIMESTAMP(start)+duration ')->rowCount();
    }

    /**
     * Makes sure that all foodsavers in regions that have master regions are also members of the master region.
     */
    public function masterRegionUpdate(): void
    {
        $foodsaver = $this->db->fetchAll('
				SELECT
				b.`id`,
				b.`name`,
				b.`type`,
				b.`master`,
				hb.foodsaver_id

				FROM 	`fs_bezirk` b,
				`fs_foodsaver_has_bezirk` hb

				WHERE 	hb.bezirk_id = b.id
				AND 	b.`master` != 0
				AND 	hb.active = 1
		');

        foreach ($foodsaver as $fs) {
            if ((int)$fs['master'] > 0) {
                $this->db->insertIgnore('fs_foodsaver_has_bezirk', [
                    'foodsaver_id' => $fs['foodsaver_id'],
                    'bezirk_id' => $fs['master'],
                    'active' => 1,
                    'added' => $this->db->now()
                ]);
            }
        }
    }

    /**
     * Lists all users that have a profile photo.
     *
     * @return array foodsaver Id and photo file name for each user
     */
    public function listUsersWithPhoto(): array
    {
        return $this->db->fetchAllByCriteria('fs_foodsaver', ['id', 'photo'], ['photo !=' => '']);
    }

    /**
     * Removes the profile photo file name for all users in the list.
     *
     * @param array $foodsaverIds a list of foodsaver IDs
     */
    public function unsetUserPhotos(array $foodsaverIds): void
    {
        $this->db->update('fs_foodsaver', ['photo' => ''], ['id' => $foodsaverIds]);
    }

    /**
     * Updates all quiz sessions that were finished or aborted more than two weeks ago by setting
     * questions and answers to null. After this the quiz results can not be seen anymore.
     *
     * @return int the number of updated entries
     */
    public function cleanOldQuizSessionData(): int
    {
        return $this->db->update(
            'fs_quiz_session',
            [
                'quiz_result' => null,
                'quiz_questions' => null,
            ],
            [
                'status' => [SessionStatus::FAILED->value, SessionStatus::PASSED->value],
                'time_end <' => Carbon::now()->subWeeks(2)->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Returns the UUIDs of all entries in the uploads table that were created in a specific interval which do not have
     * a usage type and id yet.
     *
     * @return string[]
     */
    public function listUploadsWithoutUsage(DateTime $from, DateTime $to): array
    {
        return $this->db->fetchAllValuesByCriteria('uploads', 'uuid', [
            'used_in' => null,
            'usage_id' => null,
            'uploaded_at >' => $from->format('Y-m-d H:i:s'),
            'uploaded_at <' => $to->format('Y-m-d H:i:s')
        ]);
    }
}
