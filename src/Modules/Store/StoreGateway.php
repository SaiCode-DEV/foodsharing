<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Store;

use Carbon\Carbon;
use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Map\DTO\MapMarker;
use Foodsharing\Modules\Map\DTO\StoreMarkerHelpType;
use Foodsharing\Modules\Map\DTO\StoreMarkerScopeType;
use Foodsharing\Modules\Map\DTO\StoreMarkerStatusType;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\DTO\MinimalStoreIdentifier;
use Foodsharing\Modules\Store\DTO\Store;
use Foodsharing\Modules\Store\DTO\StoreApplication;
use Foodsharing\Modules\Store\DTO\StoreInvitation;
use Foodsharing\Modules\Store\DTO\StoreTeamMembership;

class StoreGateway extends BaseGateway
{
    public function __construct(
        Database $db,
        private readonly RegionGateway $regionGateway,
    ) {
        parent::__construct($db);
    }

    public function addStore(Store $store): int
    {
        return $this->db->insert('fs_betrieb', [
            'name' => $store->name,
            'bezirk_id' => $store->region->id,
            'lat' => $store->location->lat,
            'lon' => $store->location->lon,
            'str' => $store->address->street,
            'plz' => $store->address->postalCode,
            'stadt' => $store->address->city,
            'public_info' => $store->publicInfo,
            'added' => $this->db->date($store->createdAt, false),
            'status_date' => $this->db->date($store->updatedAt, false)
        ]);
    }

    public function storeExists(int $storeId): bool
    {
        return $this->db->exists('fs_betrieb', ['id' => $storeId]);
    }

    /**
     * Return all identifiers for stores of a store chain.
     *
     * @return MinimalStoreIdentifier[]
     *
     * @throws Exception
     */
    public function findAllStoresOfStoreChain(int $chainId, Pagination $pagination = new Pagination()): array
    {
        $results = $this->db->fetchAll('SELECT id, name
            FROM fs_betrieb
            WHERE kette_id = :chainId' .
            $this->buildPaginationSqlLimit($pagination),
            $this->addPaginationSqlLimitParameters($pagination, ['chainId' => $chainId]));

        return array_map(fn (array $item) => MinimalStoreIdentifier::createFromArray($item), $results);
    }

    /**
     * Returns all information about a store if it exists.
     *
     * @param int $storeId Identifier of the store
     * @param bool $skipLoadingOfGroceries avoids loading of groceries to reduce DB load
     *
     * @throws DatabaseNoValueFoundException When store not found
     */
    public function getStore(int $storeId, bool $skipLoadingOfGroceries = false): Store
    {
        $result = $this->db->fetch(
            'SELECT	`id`,
                    `name`,
					`bezirk_id` as regionId,
					`lat`,
					`lon`,
					`str` AS street,
					`plz` AS zipCode,
					`stadt` as city,
					`public_info`,
					`public_time`,
					`betrieb_kategorie_id` as categoryId,
					`kette_id` as chainId,
					`betrieb_status_id` as cooperationStatus,
					`begin` as cooperationStart,
					`besonderheiten` as description,
					`ansprechpartner` as contactName,
					`telefon` as contactPhone,
					`fax` as contactFax,
					`email` as contactEmail,
					`prefetchtime` as calendarInterval,
					`abholmenge` as weight,
					`ueberzeugungsarbeit` as effort,
					`presse` as publicity,
					`sticker`,
					`team_status` as teamStatus,
					`use_region_pickup_rule` as useRegionPickupRule,
                    `hygiene_requirement`,
					`status_date` as updatedAt,
					`added` as createdAt
			FROM 	`fs_betrieb`

			WHERE 	`id` = :storeId
		', [
            ':storeId' => $storeId,
        ]);

        if ($result) {
            if (!$skipLoadingOfGroceries) {
                $result['groceries'] = array_column($this->getGroceries($storeId), 'id');
            }
        } else {
            throw new DatabaseNoValueFoundException();
        }

        return Store::createFromArray($result);
    }

    public function updateStoreData(Store $store, bool $groceriesChanged = false)
    {
        $this->db->update('fs_betrieb', [
            'name' => $store->name,
            'bezirk_id' => $store->region->id,

            'lat' => $store->location->lat,
            'lon' => $store->location->lon,
            'str' => $store->address->street,
            'plz' => $store->address->postalCode,
            'stadt' => $store->address->city,

            'public_info' => $store->publicInfo,
            'public_time' => $store->publicTime->value,

            'betrieb_kategorie_id' => $store->category ? $store->category->id : null,
            'kette_id' => $store->chain ? $store->chain->id : null,
            'betrieb_status_id' => $store->cooperationStatus->value,

            'besonderheiten' => $store->description,

            'ansprechpartner' => $store->contact->name,
            'telefon' => $store->contact->phone,
            'fax' => $store->contact->fax,
            'email' => $store->contact->email,
            'begin' => $store->cooperationStart ? $this->db->date($store->cooperationStart, false) : null,
            'team_status' => $store->teamStatus->value,

            'prefetchtime' => $store->calendarInterval,
            'use_region_pickup_rule' => $store->options->useRegionPickupRule,
            'abholmenge' => $store->weight,
            'ueberzeugungsarbeit' => $store->effort->value,
            'presse' => $store->publicity->value,
            'sticker' => $store->showsSticker->value,
            'hygiene_requirement' => intval($store->isHygieneRequired),
            'status_date' => $this->db->date($store->updatedAt)
        ], [
            'id' => $store->id,
        ]);

        if ($groceriesChanged) {
            $this->setGroceries($store->id, $store->groceries);
        }
    }

    public function listMyStores(int $fsId): array
    {
        return $this->db->fetchAll('
			SELECT 	b.id,
					b.name,
					b.plz,
					b.stadt,
					b.str

			FROM	fs_betrieb b
					INNER JOIN fs_betrieb_team t
					ON b.id = t.betrieb_id

			WHERE	t.foodsaver_id = :fsId
			AND     t.active = :membershipStatus
		', [
            ':fsId' => $fsId,
            ':membershipStatus' => MembershipStatus::MEMBER
        ]);
    }

    private function getStoreListQuery(): string
    {
        return '
			SELECT 	s.id,
					s.name,
					s.betrieb_status_id,
					s.kette_id,
					s.betrieb_kategorie_id,

					r.name AS region_name,

					s.added,
					s.ansprechpartner,
					s.fax,
					s.telefon,
					s.email,

					s.str AS anschrift,
					s.str,
					s.plz,
					s.stadt,
					CONCAT(s.lat,", ",s.lon) AS geo,
					s.`betrieb_status_id`,

					t.verantwortlich,
					t.active

			FROM 	fs_betrieb s
					INNER JOIN      fs_bezirk r        ON  r.id = s.bezirk_id
					LEFT OUTER JOIN fs_betrieb_team t  ON  t.betrieb_id = s.id
		';
    }

    /**
     * @param ?int $userId if set, include all own stores (from any region) in output
     * @param ?int $addFromRegionId if set, include all stores (own or otherwise) from given region in output
     * @param bool $sortByOwnTeamStatus if true, split the resulting stores into multiple categories (depending on own team status)
     */
    public function getMyStores(?int $userId, ?int $addFromRegionId = null, bool $sortByOwnTeamStatus = true): array
    {
        $query = $this->getStoreListQuery();

        $betriebe = [];

        if (!is_null($userId)) {
            $betriebe = $this->db->fetchAll($query . '
				WHERE    t.foodsaver_id = :userId

				ORDER BY t.verantwortlich DESC, s.name ASC
			', [
                ':userId' => $userId,
            ]);
        }

        if ($sortByOwnTeamStatus) {
            $result = [
                'verantwortlich' => [],
                'team' => [],
                'waitspringer' => [],
                'requested' => [],
                'sonstige' => [],
            ];
        } else {
            $result = [];
        }

        $already_in = [];

        foreach ($betriebe as $b) {
            $already_in[$b['id']] = true;

            if ($sortByOwnTeamStatus) {
                if ($b['verantwortlich'] == 0) {
                    if ($b['active'] == MembershipStatus::APPLIED_FOR_TEAM) {
                        $result['requested'][] = $b;
                    } elseif ($b['active'] == MembershipStatus::MEMBER) {
                        $result['team'][] = $b;
                    } elseif ($b['active'] == MembershipStatus::JUMPER) {
                        $result['waitspringer'][] = $b;
                    }
                } else {
                    $result['verantwortlich'][] = $b;
                }
            } else {
                $result[$b['id']] = $b;
            }
        }

        if ($addFromRegionId !== null) {
            $child_region_ids = $this->regionGateway->listIdsForDescendantsAndSelf($addFromRegionId);
            if (!empty($child_region_ids)) {
                $placeholders = $this->db->generatePlaceholders(count($child_region_ids));
                $betriebe = $this->db->fetchAll(
                    $query . ' WHERE bezirk_id IN(' . $placeholders . ') ORDER BY r.name DESC',
                    $child_region_ids
                );

                foreach ($betriebe as $b) {
                    if (!isset($already_in[$b['id']])) {
                        $already_in[$b['id']] = true;
                        if ($sortByOwnTeamStatus) {
                            $result['sonstige'][] = $b;
                        } else {
                            $result[$b['id']] = $b;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @deprecated The function is too complex. It needs be replaced with a function that uses DTOs and the logic
     * should be moved into a transaction class.
     */
    public function getMyStore(int $fs_id, int $storeId): array
    {
        $result = $this->db->fetch('
			SELECT
        			b.`id`,
        			b.`betrieb_status_id`,
        			b.`bezirk_id`,
        			b.`plz`,
    				b.`stadt`,
        			b.`lat`,
        			b.`lon`,
        			b.`kette_id`,
        			b.`betrieb_kategorie_id`,
        			b.`name`,
        			b.`str`,
        			b.`status_date`,
        			b.`status`,
        			b.`ansprechpartner`,
        			b.`telefon`,
        			b.`fax`,
        			b.`email`,
        			b.`begin`,
        			b.`besonderheiten`,
        			b.`public_info`,
        			b.`public_time`,
        			b.`ueberzeugungsarbeit`,
        			b.`presse`,
        			b.`sticker`,
        			b.`abholmenge`,
        			b.`team_status`,
        			b.`prefetchtime`,
        			b.`team_conversation_id`,
        			b.`springer_conversation_id`,
        			b.`use_region_pickup_rule`,
                    b.lat,
                    b.lon,
                    b.hygiene_requirement,
        			count(DISTINCT(a.date)) AS pickup_count

			FROM 	`fs_betrieb` b

			LEFT JOIN `fs_abholer` a
			ON a.betrieb_id = b.id
			AND a.date < CURDATE()

			WHERE b.`id` = :storeId

			GROUP BY b.`id`
        ', [
            ':storeId' => $storeId
        ]);

        if ($result) {
            $result['foodsaver'] = $this->getStoreTeam($storeId);
            $result['springer'] = $this->getStoreTeam($storeId, [MembershipStatus::JUMPER]);
            $result['verantwortlich'] = false;
            $result['team'] = [];
            $result['jumper'] = false;

            if (!empty($result['springer'])) {
                foreach ($result['springer'] as $v) {
                    if ($v['id'] == $fs_id) {
                        $result['jumper'] = true;
                    }
                }
            }

            if (empty($result['foodsaver'])) {
                $result['foodsaver'] = [];
            } else {
                $result['team'] = [];
                foreach ($result['foodsaver'] as $v) {
                    $result['team'][] = [
                        'id' => $v['id'],
                        'value' => $v['name']
                    ];
                    if ($v['verantwortlich'] == 1) {
                        $result['verantwortlicher'] = $v['id'];
                        if ($v['id'] == $fs_id) {
                            $result['verantwortlich'] = true;
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function getGroceries(int $storeId): array
    {
        return $this->db->fetchAll('
        	SELECT  l.`id`,
        			l.name

        	FROM 	`fs_betrieb_has_lebensmittel` hl
        			INNER JOIN `fs_lebensmittel` l
        	        ON l.id = hl.lebensmittel_id

        	WHERE 	`betrieb_id` = :storeId
        ', [
            ':storeId' => $storeId
        ]);
    }

    private function setGroceries(int $storeId, array $foodTypeIds): int
    {
        $this->db->delete('fs_betrieb_has_lebensmittel', ['betrieb_id' => $storeId]);

        $newFoodData = array_map(fn ($foodId) => ['betrieb_id' => $storeId, 'lebensmittel_id' => $foodId], $foodTypeIds);

        return $this->db->insertMultiple('fs_betrieb_has_lebensmittel', $newFoodData);
    }

    /**
     * @return StoreApplication[] all foodsavers that currently apply to the store team
     */
    public function getApplications(int $storeId, GeoLocation $storePosition): array
    {
        $applications = $this->db->fetchAll('SELECT
                foodsaver.id,
                foodsaver.photo,
                foodsaver.name,
                foodsaver.nachname,
                foodsaver.is_sleeping,
                foodsaver.verified,
                IFNULL(ROUND(ST_DISTANCE_SPHERE(
                    Point(NULLIF(foodsaver.lon, ""), NULLIF(foodsaver.lat, "")),
                    Point(:storeLon, :storeLat)
                ) / 1000), -1) AS distance,
                log.date_activity,
                log.content
            FROM fs_betrieb_team betrieb_team
            INNER JOIN fs_foodsaver foodsaver
                ON foodsaver.id = betrieb_team.foodsaver_id
            LEFT OUTER JOIN (
                SELECT log.fs_id_a, MAX(log.date_activity) AS max_date_activity
                FROM fs_store_log log
                WHERE log.store_id = :storeId1
                GROUP BY log.fs_id_a
            ) latest_applications_date
                ON latest_applications_date.fs_id_a = betrieb_team.foodsaver_id
            LEFT OUTER JOIN fs_store_log log
                ON log.store_id = betrieb_team.betrieb_id
                AND log.fs_id_a = latest_applications_date.fs_id_a
                AND log.date_activity = latest_applications_date.max_date_activity
            WHERE betrieb_team.betrieb_id = :storeId2
                AND betrieb_team.active = :membershipStatus
                AND foodsaver.deleted_at IS NULL
            ORDER BY log.date_activity DESC
		', [
            ':storeLat' => $storePosition->lat,
            ':storeLon' => $storePosition->lon,
            ':storeId1' => $storeId,
            ':storeId2' => $storeId,
            ':membershipStatus' => MembershipStatus::APPLIED_FOR_TEAM,
        ]);

        return array_map(StoreApplication::createFromArray(...), $applications);
    }

    /**
     * @return StoreInvitation[] all foodsavers that are currently invited to the store team
     */
    public function getInvitations(int $storeId): array
    {
        $invitations = $this->db->fetchAll('SELECT
                foodsaver.id,
                foodsaver.photo,
                foodsaver.name,
                foodsaver.is_sleeping,
                foodsaver.verified,
                log.date_activity,
                inviter.id AS inviter_id,
                inviter.name AS inviter_name
            FROM fs_betrieb_team betrieb_team
            INNER JOIN fs_foodsaver foodsaver
                ON foodsaver.id = betrieb_team.foodsaver_id
            LEFT OUTER JOIN (
                SELECT log.fs_id_p, MAX(log.date_activity) AS max_date_activity
                FROM fs_store_log log
                WHERE log.store_id = :storeId1
                AND log.action = :actionType
                GROUP BY log.fs_id_p
            ) latest_invitation_date
                ON latest_invitation_date.fs_id_p = betrieb_team.foodsaver_id
            LEFT OUTER JOIN fs_store_log log
                ON log.store_id = betrieb_team.betrieb_id
                AND log.fs_id_p = latest_invitation_date.fs_id_p
                AND log.date_activity = latest_invitation_date.max_date_activity
                AND latest_invitation_date.fs_id_p = betrieb_team.foodsaver_id
            LEFT OUTER JOIN fs_foodsaver inviter
                ON inviter.id = log.fs_id_a
            WHERE betrieb_team.betrieb_id = :storeId2
                AND betrieb_team.active = :membershipStatus
                AND foodsaver.deleted_at IS NULL
            ORDER BY log.date_activity DESC
		', [
            ':storeId1' => $storeId,
            ':storeId2' => $storeId,
            ':membershipStatus' => MembershipStatus::INVITED,
            ':actionType' => StoreLogAction::INVITED_TO_TEAM,
        ]);

        return array_map(StoreInvitation::createFromArray(...), $invitations);
    }

    public function getStoreName(int $storeId): string
    {
        return $this->db->fetchValueByCriteria('fs_betrieb', 'name', ['id' => $storeId]);
    }

    public function getStoreRegionId(int $storeId): int
    {
        return $this->db->fetchValueByCriteria('fs_betrieb', 'bezirk_id', ['id' => $storeId]);
    }

    public function getBasics_groceries(): array
    {
        return $this->db->fetchAll('
			SELECT 	`id`,
					`name`
			FROM 	`fs_lebensmittel`
			ORDER BY `name`
		');
    }

    public function getBasics_chain(): array
    {
        return $this->db->fetchAll('
			SELECT	`id`,
					`name`
			FROM 	`fs_chain`
			ORDER BY `name`
		');
    }

    public function existStoreChain(int $id): bool
    {
        return $this->db->exists('fs_chain', ['id' => $id]);
    }

    public function getStoreTeam($storeId, array $membershipStatuses = [MembershipStatus::MEMBER], bool $includeDistance = false, GeoLocation $storePosition = null): array
    {
        $params = [
            AchievementIDs::HYGIENE_CERTIFICATE,
            $storeId,
            ...$membershipStatuses,
        ];
        if ($includeDistance) {
            array_unshift($params, $storePosition->lon, $storePosition->lat);
        }

        return $this->db->fetchAll('SELECT
                fs.`id`,
                fs.`verified`,
                fs.`active`,
                fs.`telefon`,
                fs.`handy`,
                fs.`photo`,
                fs.`rolle`,
                fs.`name` AS firstName,
                CONCAT(fs.name," ",fs.nachname) AS name,
                t.`active` AS team_active,
                t.`verantwortlich`,
                t.`stat_last_update`,
                t.`stat_fetchcount`,
                t.`stat_first_fetch`,
                t.`stat_add_date`,
                UNIX_TIMESTAMP(t.`stat_last_fetch`) AS last_fetch,
                UNIX_TIMESTAMP(t.`stat_add_date`) AS add_date,
                fs.`is_sleeping`,
                ' . ($includeDistance ? 'IFNULL(ROUND(ST_DISTANCE_SPHERE(
                    Point(NULLIF(fs.lon, ""), NULLIF(fs.lat, "")),
                    Point(?, ?)
                ) / 1000), -1) AS distance,' : '') . "
                IF(a.achievement_id IS NULL, NULL, IFNULL(a.`valid_until`, 'infinite')) AS hygiene_certificate_until
            FROM `fs_betrieb_team` t
            INNER JOIN `fs_foodsaver` fs
                ON fs.id = t.foodsaver_id
            LEFT OUTER JOIN `fs_foodsaver_has_achievement` a
                ON a.foodsaver_id = fs.id
                AND (a.valid_until IS NULL OR a.valid_until >= NOW())
                AND a.achievement_id = ?
            WHERE `betrieb_id` = ?
                AND t.active IN ({$this->db->generatePlaceholders(count($membershipStatuses))})
                AND fs.deleted_at IS NULL
            ORDER BY fs.id
        ", $params);
    }

    public function isStoreTeamMemberOfStoreChainStore(int $fsId): bool
    {
        return $this->db->fetch('
				SELECT COUNT(*) as count
				FROM `fs_betrieb_team` t
				INNER JOIN `fs_betrieb` b
				     	ON b.id = t.betrieb_id
				WHERE	t.foodsaver_id = :fsId
				AND 	t.active = :membershipStatus
                AND     b.kette_id IS NOT NULL
		', [
            ':fsId' => $fsId,
            ':membershipStatus' => MembershipStatus::MEMBER
        ])['count'] != 0;
    }

    public function getBiebsForStore($storeId)
    {
        return $this->db->fetchAll('
			SELECT 	`foodsaver_id` as id

			FROM fs_betrieb_team

			WHERE `betrieb_id` = :betrieb_id
			AND verantwortlich = 1
			AND `active` = :membershipStatus
        ', [
            ':betrieb_id' => $storeId,
            ':membershipStatus' => MembershipStatus::MEMBER
        ]);
    }

    /**
     * Returns all managers of a store.
     */
    public function getStoreManagers(int $storeId): array
    {
        return $this->db->fetchAllValues('
			SELECT 	t.`foodsaver_id`,
					t.`verantwortlich`

			FROM 	`fs_betrieb_team` t
					INNER JOIN  `fs_foodsaver` fs
					ON fs.id = t.foodsaver_id

			WHERE 	t.`betrieb_id` = :storeId
					AND t.verantwortlich = 1
					AND fs.deleted_at IS NULL
		', [
            ':storeId' => $storeId,
        ]);
    }

    public function getAllStoreManagers(): array
    {
        $verant = $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`email`

			FROM 	`fs_foodsaver` fs
					INNER JOIN `fs_betrieb_team` bt
			        ON bt.foodsaver_id = fs.id

			WHERE 	bt.verantwortlich = 1
			AND		fs.deleted_at IS NULL
		');

        $result = [];
        foreach ($verant as $v) {
            $result[$v['id']] = $v;
        }

        return $result;
    }

    public function getUseRegionPickupRule(int $storeId)
    {
        return $this->db->fetchValueByCriteria('fs_betrieb', 'use_region_pickup_rule', ['id' => $storeId]);
    }

    public function getStoreTeamStatus(int $storeId): TeamSearchStatus
    {
        return TeamSearchStatus::tryFrom($this->db->fetchValueByCriteria('fs_betrieb', 'team_status', ['id' => $storeId]));
    }

    public function getUserTeamStatus(int $userId, int $storeId): int
    {
        $result = $this->db->fetchByCriteria('fs_betrieb_team', [
            'active',
            'verantwortlich'
        ], [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId
        ]);

        if ($result) {
            if ($result['verantwortlich'] && $result['active'] == MembershipStatus::MEMBER) {
                return TeamStatus::Coordinator;
            } else {
                return match ($result['active']) {
                    MembershipStatus::JUMPER => TeamStatus::WaitingList,
                    MembershipStatus::MEMBER => TeamStatus::Member,
                    MembershipStatus::INVITED => TeamStatus::Invited,
                    default => TeamStatus::Applied,
                };
            }
        }

        return TeamStatus::NoMember;
    }

    public function getStoreRequiresHygiene(int $storeId): bool
    {
        return boolval($this->db->fetchValueById('fs_betrieb', 'hygiene_requirement', $storeId));
    }

    public function getBetriebConversation(int $storeId, bool $springerConversation = false): ?int
    {
        if ($springerConversation) {
            $chatType = 'springer_conversation_id';
        } else {
            $chatType = 'team_conversation_id';
        }

        return $this->db->fetchValueByCriteria('fs_betrieb', $chatType, ['id' => $storeId]);
    }

    /**
     * retrieves all store managers for a given region (by being store manager in a store that is part of that region,
     * which is semantically not the same we use on platform).
     */
    public function getStoreManagersOf(int $regionId): array
    {
        return $this->db->fetchAllValues('
            SELECT DISTINCT
                    bt.foodsaver_id

            FROM    `fs_bezirk_closure` c
			        INNER JOIN `fs_betrieb` b
                    ON c.bezirk_id = b.bezirk_id
			            INNER JOIN `fs_betrieb_team` bt
                        ON bt.betrieb_id = b.id
			                INNER JOIN `fs_foodsaver` fs
                            ON fs.id = bt.foodsaver_id

			WHERE   c.ancestor_id = :regionId
            AND     bt.verantwortlich = 1
            AND     fs.deleted_at IS NULL
        ', [
            ':regionId' => $regionId
        ]);
    }

    /**
     * Returns a list with all store memberships of the foodsaver.
     *
     * @param int $fsId Foodsharer Id
     * @param CooperationStatus[] $storeCooperationStates All store state should should be contained @see CooperationStatus
     *
     * @return StoreTeamMembership[] Returns a array of memberships
     */
    public function listAllStoreTeamMembershipsForFoodsaver(int $fsId, array $storeCooperationStates = [])
    {
        if ($fsId == 0) {
            return [];
        }

        $queryParams = [$fsId];
        $query = '
			SELECT 	b.id as store_id,
					b.name as store_name,
					bt.verantwortlich AS managing,
					bt.active as membership_status
			FROM fs_betrieb_team bt
				INNER JOIN fs_betrieb b
					ON bt.betrieb_id = b.id
			WHERE   bt.`foodsaver_id` = ?
        ';

        if (!empty($storeCooperationStates)) {
            $inPlaceHolder = implode(', ', array_fill(0, count($storeCooperationStates), '?'));
            $query .= 'AND 	b.betrieb_status_id IN (' . $inPlaceHolder . ')
			';
            array_push($queryParams, array_map(
                fn (CooperationStatus $state) => $state->value,
                $storeCooperationStates
            )
            );
        }
        $query .= 'ORDER BY bt.verantwortlich DESC, membership_status ASC, b.name ASC
		';

        $rows = $this->db->fetchAll($query, $queryParams);

        $results = [];
        foreach ($rows as $row) {
            $results[] = StoreTeamMembership::createFromArray($row);
        }

        return $results;
    }

    public function listStoreIds($fsId)
    {
        return $this->db->fetchAllValuesByCriteria('fs_betrieb_team', 'betrieb_id', ['foodsaver_id' => $fsId]);
    }

    public function listStoreIdsWhereResponsible($fsId)
    {
        return $this->db->fetchAllByCriteria('fs_betrieb_team', ['betrieb_id'], ['foodsaver_id' => $fsId, 'verantwortlich' => 1]);
    }

    public function updateStoreRegion(int $storeId, int $regionId): int
    {
        return $this->db->update('fs_betrieb', ['bezirk_id' => $regionId], ['id' => $storeId]);
    }

    public function updateStoreConversation(int $storeId, int $conversationId, bool $isStandby): int
    {
        $fieldToUpdate = $isStandby ? 'springer_conversation_id' : 'team_conversation_id';

        return $this->db->update('fs_betrieb', [$fieldToUpdate => $conversationId], ['id' => $storeId]);
    }

    public function getStoreByConversationId(int $id): ?array
    {
        $store = $this->db->fetch('
			SELECT	id,
					name

			FROM	fs_betrieb

			WHERE	team_conversation_id = :memberId
			OR      springer_conversation_id = :jumperId
		', [
            ':memberId' => $id,
            ':jumperId' => $id
        ]);

        return $store;
    }

    public function addStoreLog(
        int $store_id,
        int $foodsaver_id,
        ?int $fs_id_p,
        ?\DateTimeInterface $dateReference,
        int $action,
        ?string $content = null,
        ?string $reason = null
    ) {
        return $this->db->insert('fs_store_log', [
            'store_id' => $store_id,
            'action' => $action,
            'fs_id_a' => $foodsaver_id,
            'fs_id_p' => $fs_id_p,
            'date_reference' => $dateReference ? $dateReference->format('Y-m-d H:i:s') : null,
            'content' => $content ? strip_tags($content) : '',
            'reason' => $reason ? strip_tags($reason) : ''
        ]);
    }

    public function getAllStores(array $listOfcooperationStatus = []): array
    {
        $where = '';
        $countStates = count($listOfcooperationStatus);
        if ($countStates != 0) {
            $cooperationStatusPlaceHolder = implode(',', array_fill(0, $countStates, '?'));

            $where = 'WHERE b.betrieb_status_id IN (' . $cooperationStatusPlaceHolder . ');';
        }
        $values = array_map(
            fn (CooperationStatus $status) => $status->value,
            $listOfcooperationStatus);

        return $this->db->fetchAll(
            'SELECT
                b.`id`, b.`name`
            FROM
                fs_betrieb b ' . $where,
            $values);
    }

    /**
     * Return all stores.
     * Can be restricted to stores the user is a member of.
     *
     * @return array name and id of the stores
     */
    public function getStores(int $fs_id = null, array $cooperationStatus = []): array
    {
        if ($fs_id) {
            return $this->db->fetchAll('SELECT
				b.id,
				b.`name`
			FROM
				fs_betrieb_team t
			JOIN fs_betrieb b ON
				b.id = t.betrieb_id AND b.betrieb_status_id = :established
			WHERE
				t.foodsaver_id = :fs_id AND t.active = :membership_status
			', [
                'fs_id' => $fs_id,
                'membership_status' => MembershipStatus::MEMBER,
                ':established' => CooperationStatus::COOPERATION_ESTABLISHED->value,
            ]);
        } else {
            return $this->getAllStores($cooperationStatus);
        }
    }

    public function addStoreRequest(int $storeId, int $userId): int
    {
        return $this->db->insertOrUpdate('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
            'verantwortlich' => 0,
            'active' => MembershipStatus::APPLIED_FOR_TEAM,
        ]);
    }

    public function addStoreInvitation(int $storeId, int $userId): int
    {
        return $this->db->insertOrUpdate('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
            'verantwortlich' => 0,
            'active' => MembershipStatus::INVITED,
        ]);
    }

    public function removeStoreInvitation(int $storeId, int $userId): int
    {
        return $this->db->delete('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
            'verantwortlich' => 0,
            'active' => MembershipStatus::INVITED,
        ]);
    }

    /**
     * Add store manager to a store and make her responsible for that store.
     */
    public function addStoreManager(int $storeId, int $userId): int
    {
        return $this->db->insertOrUpdate('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
            'verantwortlich' => 1,
            'active' => MembershipStatus::MEMBER,
        ]);
    }

    public function removeStoreManager(int $storeId, int $userId): int
    {
        return $this->db->update('fs_betrieb_team', [
            'verantwortlich' => 0,
        ], [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
        ]);
    }

    public function addUserToTeam(int $storeId, int $userId): void
    {
        $this->db->insertOrUpdate('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId,
            'stat_add_date' => $this->db->now(),
            'active' => MembershipStatus::MEMBER,
        ]);
    }

    /**
     * @param int $newStatus a Core\DBConstants\StoreTeam\MembershipStatus
     */
    public function setUserMembershipStatus(int $storeId, int $userId, int $newStatus): void
    {
        $this->db->update('fs_betrieb_team', ['active' => $newStatus], [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId
        ]);
    }

    public function removeUserFromTeam(int $storeId, int $userId): void
    {
        $this->db->delete('fs_betrieb_team', [
            'betrieb_id' => $storeId,
            'foodsaver_id' => $userId
        ]);
    }

    /**
     * Returns a list of stores which belong to regions.
     *
     * @return array<Store>
     *
     * @throws Exception
     */
    public function listStoresInRegion(int $regionId, bool $includeSubregions = false): array
    {
        $regionIds = [$regionId];
        if ($includeSubregions) {
            $regionIds = array_merge($regionIds, $this->regionGateway->listIdsForDescendantsAndSelf($regionId));
        }

        $placeholders = implode(',', array_fill(0, count($regionIds), '?'));
        $results = $this->db->fetchAll($this->sqlSelectStoreColumns() . '
            FROM fs_betrieb,
                fs_bezirk
            WHERE 	fs_betrieb.bezirk_id = fs_bezirk.id
            AND 	fs_betrieb.bezirk_id IN(' . $placeholders . ')
		', $regionIds);

        return array_map(fn ($store) => Store::createFromArray($store), $results);
    }

    /**
     * Returns a list of stores where the user is a member.
     *
     * @return array<Store>
     *
     * @throws Exception
     */
    public function listStoresInFromUser(int $fs_id = null, array $cooperationStatus = []): array
    {
        $results = $this->db->fetchAll($this->sqlSelectStoreColumns() . '
            FROM fs_betrieb_team
            JOIN fs_betrieb ON
                fs_betrieb.id = fs_betrieb_team.betrieb_id
            WHERE fs_betrieb_team.foodsaver_id = :fs_id
    ', [
                'fs_id' => $fs_id
        ]);

        return array_map(fn ($store) => Store::createFromArray($store), $results);
    }

    public function getStoreLogsByActionType(int $storeId, array $storeActions, Carbon $fromDate, Carbon $toDate, Pagination $pagination): array
    {
        $logEntries = $this->db->fetchAll('SELECT
				DATE_FORMAT(CONVERT_TZ(date_activity, "' . TIME_ZONE . '", "UTC"), "%Y-%m-%dT%TZ") as performed_at,
				action as action_id,
				fs_id_a as acting_foodsaver_id,
				fs_id_p as affected_foodsaver_id,
				DATE_FORMAT(CONVERT_TZ(date_reference, "' . TIME_ZONE . '", "UTC"), "%Y-%m-%dT%TZ") as date_reference,
				content,
				reason
			FROM
				fs_store_log
			WHERE
				store_id = ?
                AND date_activity >= ?
                AND date_activity <= ?
                AND action IN (' . $this->db->generatePlaceholders(count($storeActions)) . ')
            ORDER BY performed_at DESC
            LIMIT ?, ?
		    ',
            [$storeId, $fromDate, $toDate, ...$storeActions, $pagination->offset, $pagination->pageSize]);

        return $logEntries;
    }

    public function listRegionStoresActivePickupRule(int $regionId): array
    {
        return $this->db->fetchAll(
            'select  id as storeId,
        				   name as storeName
					from fs_betrieb b
					where b.bezirk_id = :regionId
					  and b.use_region_pickup_rule',
            [':regionId' => $regionId]
        );
    }

    /**
     * Provides Stores with position markers.
     *
     * @return MapMarker[]
     */
    public function getStoreMarkers(int $userId, StoreMarkerStatusType $status, StoreMarkerHelpType $help, StoreMarkerScopeType $scope): array
    {
        $query = 'SELECT b.id, b.lat, b.lon, b.name FROM fs_betrieb b';
        $conditions = [
            'b.lat != ""',
            'b.lon != ""',
            'b.betrieb_status_id != :deletedStatus',
        ];
        $params = [':deletedStatus' => CooperationStatus::PERMANENTLY_CLOSED->value];

        if ($scope === StoreMarkerScopeType::MEMBER) {
            $query .= ' INNER JOIN fs_betrieb_team t ON b.id = t.betrieb_id';
            $conditions[] = 't.foodsaver_id = :userId';
            $conditions[] = 't.active >= :memberStatus';
            array_push($params, [':userId' => $userId, ':memberStatus' => MembershipStatus::MEMBER]);
        } elseif ($scope === StoreMarkerScopeType::REGION) {
            $query .= ' INNER JOIN fs_foodsaver_has_bezirk r ON r.bezirk_id = b.bezirk_id';
            $conditions[] = 'r.foodsaver_id = :userId';
            $params[] = [':userId' => $userId];
        }

        if ($status !== StoreMarkerStatusType::ALL) {
            $operator = $status === StoreMarkerStatusType::COOPERATING ? '=' : '!=';
            $conditions[] = "b.betrieb_status_id {$operator} :cooperatingStatus";
            $params[':cooperatingStatus'] = CooperationStatus::COOPERATION_ESTABLISHED->value;
        }

        if ($help !== StoreMarkerHelpType::ALL) {
            $conditions[] = 'b.team_status >= :searchStatus';
            $params[':searchStatus'] = $help === StoreMarkerHelpType::OPEN ? TeamSearchStatus::OPEN->value : TeamSearchStatus::OPEN_SEARCHING->value;
        }

        $query .= ' WHERE ' . implode(' AND ', $conditions);
        $markers = $this->db->fetchAll($query, $params);

        return array_map(MapMarker::createFromArray(...), $markers);
    }

    public function getStoresForUser(int $userId, array $columns): array
    {
        $select = implode(', ', array_map(fn ($column) => "store.`$column`", $columns));

        return $this->db->fetchAll('SELECT
            ' . $select . '
            FROM fs_betrieb store
            JOIN fs_betrieb_team team ON team.betrieb_id = store.id 
            WHERE team.foodsaver_id = :userId
            AND team.active = :activeStatus',
            [
                ':userId' => $userId,
                ':activeStatus' => MembershipStatus::MEMBER,
            ]);
    }

    private function sqlSelectStoreColumns()
    {
        return 'SELECT
                    fs_betrieb.id,
                    fs_betrieb.name,
                    fs_betrieb.bezirk_id as regionId,
                    fs_betrieb.lat,
                    fs_betrieb.lon,
                    fs_betrieb.str AS street,
                    fs_betrieb.plz AS zipCode,
                    fs_betrieb.stadt as city,
                    fs_betrieb.public_info,
                    fs_betrieb.public_time,
                    fs_betrieb.betrieb_kategorie_id as categoryId,
                    fs_betrieb.kette_id as chainId,
                    fs_betrieb.betrieb_status_id as cooperationStatus,
                    fs_betrieb.begin as cooperationStart,
                    fs_betrieb.besonderheiten as description,
                    fs_betrieb.ansprechpartner as contactName,
                    fs_betrieb.telefon as contactPhone,
                    fs_betrieb.fax as contactFax,
                    fs_betrieb.email as contactEmail,
                    fs_betrieb.prefetchtime as calendarInterval,
                    fs_betrieb.abholmenge as weight,
                    fs_betrieb.ueberzeugungsarbeit as effort,
                    fs_betrieb.presse as publicity,
                    fs_betrieb.sticker,
                    fs_betrieb.team_status as teamStatus,
                    fs_betrieb.use_region_pickup_rule as useRegionPickupRule,
                    fs_betrieb.hygiene_requirement,
                    fs_betrieb.status_date as updatedAt,
                    fs_betrieb.added as createdAt';
    }
}
