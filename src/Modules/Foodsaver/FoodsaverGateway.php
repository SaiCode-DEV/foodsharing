<?php

namespace Foodsharing\Modules\Foodsaver;

use Carbon\Carbon;
use DateInterval;
use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Foodsaver\DTO\EditableProfileDTO;
use Foodsharing\Modules\Map\DTO\MapMarker;
use Foodsharing\Modules\Map\DTO\UserMarkerActivityType;
use Foodsharing\Modules\Map\DTO\UserMarkerMemberType;
use Foodsharing\Modules\Map\DTO\UserMarkerRoleType;
use Foodsharing\Modules\Region\ForumFollowerGateway;
use Foodsharing\Utility\DataHelper;

class FoodsaverGateway extends BaseGateway
{
    private readonly DataHelper $dataHelper;
    private readonly ForumFollowerGateway $forumFollowerGateway;

    public function __construct(
        Database $db,
        ForumFollowerGateway $forumFollowerGateway,
        DataHelper $dataHelper,
    ) {
        parent::__construct($db);

        $this->dataHelper = $dataHelper;
        $this->forumFollowerGateway = $forumFollowerGateway;
    }

    /**
     * @return Profile[]
     */
    public function getFoodsaversByRegion(int $regionId, bool $hideRecentlyOnline = false): array
    {
        $onlyInactiveClause = '';
        if ($hideRecentlyOnline) {
            $oldestActiveDate = Carbon::now()->subMonths(6)->format('Y-m-d H:i:s');
            $onlyInactiveClause = '
				AND (fs.last_login < "' . $oldestActiveDate . '"
					OR fs.last_login IS NULL)
			';
        }

        $result = $this->db->fetchAll('
		    SELECT	fs.id,
					fs.name,
					fs.photo,
					fs.is_sleeping

		    FROM	fs_foodsaver fs
					INNER JOIN fs_foodsaver_has_bezirk fsreg
					ON fs.id = fsreg.foodsaver_id

		    WHERE   fs.deleted_at IS NULL
			AND     fsreg.bezirk_id = :regionId'
                    . $onlyInactiveClause . '

			ORDER BY fs.name ASC
		', [
            ':regionId' => $regionId
        ]);

        return array_map(fn ($item) => new Profile($item), $result);
    }

    /**
     * @return UnitMember[]
     * @throws Exception
     */
    public function listActiveFoodsaversByRegion(int $regionId, bool $includeAdminFields): array
    {
        $users = $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`photo`,
					fs.`name`,
					fs.`nachname` as lastname,
					fs.is_sleeping,
					fs.rolle as role,
					fs.last_login as last_activity,
					fs.anmeldedatum as registration_date,
					fs.verified,
					fs.last_pass,
					(fs.bezirk_id = ?) AS is_home_region,
                    if (isnull(fsbot.`bezirk_id`) , false, true) as isAdminOrAmbassadorOfRegion

		    FROM	fs_foodsaver fs
					INNER JOIN fs_foodsaver_has_bezirk fsreg
					ON fs.id = fsreg.foodsaver_id
				    left outer join fs_botschafter fsbot
					on fsreg.foodsaver_id = fsbot.foodsaver_id and fsreg.bezirk_id = fsbot.bezirk_id

			WHERE   fs.deleted_at IS NULL
			AND 	fsreg.active = 1
			AND 	fsreg.bezirk_id = ?

			ORDER BY fs.`name`
		', [$regionId, $regionId]);

        $resultClass = $includeAdminFields ? UnitMemberForAdmin::class : UnitMember::class;

        return array_map(fn ($user) => $resultClass::createFromArray($user), $users);
    }

    public function listActiveWithFullNameByRegion(int $regionId): array
    {
        return $this->db->fetchAll('
			SELECT 	fs.id,
					CONCAT(fs.`name`, " ", fs.`nachname`) AS `name`,
					fs.`email`,
					fs.`geschlecht`

		    FROM	fs_foodsaver fs
					INNER JOIN fs_foodsaver_has_bezirk fsreg
					ON fs.id = fsreg.foodsaver_id

			WHERE   fs.deleted_at IS NULL
			AND 	fs.last_login >= CURDATE() - INTERVAL 6 MONTH
			AND 	fsreg.active = 1
			AND 	fsreg.bezirk_id = :regionId
			AND     fsreg.notify_by_email_about_new_threads = 1
		', [
            ':regionId' => $regionId
        ]);
    }

    /**
     * @deprecated This returns a mixed array. Use getFoodsaverSettings instead, which returns an object.
     */
    public function getFoodsaverDetails(int $fsId): array
    {
        return $this->db->fetch('
		SELECT
			fs.id,
			fs.position,
			fs.bezirk_id,
			fs.photo,
			fs.rolle,
			fs.verified,
			fs.name,
			fs.nachname,
            fs.is_sleeping,
			fs.lat,
			fs.lon,
			fs.email,
			fs.token,
			fs.mailbox_id,
			fs.geschlecht,
			fs.last_login as last_activity,
			fs.geb_datum,
			fs.handy as mobile,
			fs.telefon as phone,
			fs.anschrift as street,
			fs.plz as postalCode,
			fs.stadt as city,
			fs.about_me_public,
			fs.about_me_intern,
			fs.no_automatic_delete,
			fs.totp_secret,
			fs.backup_codes

		FROM	fs_foodsaver fs

		WHERE     fs.id = :id
		AND       fs.deleted_at IS NULL
		', [':id' => $fsId]);
    }

    /**
     * Returns the home region id of the foodsaver.
     *
     * @return int RegionId or 0 for not set home region (DB default)
     */
    public function getHomeRegionOfFoodsaver(int $foodsaverId): int
    {
        return $this->db->fetchValueById('fs_foodsaver', 'bezirk_id', $foodsaverId);
    }

    public function getCountCommonStores(int $fs_viewer, int $fs_viewed): int
    {
        $stm = '
				SELECT COUNT(*) as count
				FROM (
					SELECT betrieb_id
						FROM fs_betrieb_team
					WHERE foodsaver_id = :fs_viewer
						AND ACTIVE = :member_status_viewer
						INTERSECT
					SELECT betrieb_id
							FROM fs_betrieb_team
					WHERE foodsaver_id = :fs_viewed) a
		';

        $res = $this->db->fetchAll($stm, [
            ':fs_viewer' => $fs_viewer,
            ':fs_viewed' => $fs_viewed,
            'member_status_viewer' => MembershipStatus::MEMBER
        ]);

        return $res['0']['count'];
    }

    public function getFoodsaverBasics(int $fsId): array
    {
        $fs = $this->db->fetchByCriteria('fs_foodsaver', [
            'id',
            'name',
            'nachname',
            'bezirk_id',
            'rolle',
            'photo',
            'geschlecht',
            'stat_fetchweight',
            'stat_fetchcount',
            'stat_givecount',
            'stat_engagecount',
            'is_sleeping'
        ], [
            'id' => $fsId
        ]);

        return $fs ?: [];
    }

    /**
     * Checks if the profile of a Foodsaver is complete.
     *
     * This method verifies that the profile of a Foodsaver identified by the given
     * Foodsaver ID ($fsId) has all the required fields set to non-empty values.
     * The required fields are:
     * - firstName (name)
     * - lastName (nachname)
     * - date of birth (geb_datum)
     * - address (anschrift)
     * - city (stadt)
     * - postal code (plz)
     * - latitude and longitude (lat, lon)
     * - avatar (photo)
     *
     * @param int $fsId the ID of the foodsaver
     * @return bool returns true if the profile is complete, false otherwise
     */
    public function isProfileComplete(int $fsId): bool
    {
        // note: getFoodsaverDetailsBasic is not sufficient as it does not
        // include photo and geb_datum
        $fs = $this->getFoodsaver($fsId);

        return !empty($fs['name']) &&
            !empty($fs['nachname']) &&
            !empty($fs['geb_datum']) &&
            !empty($fs['anschrift']) &&
            !empty($fs['stadt']) &&
            !empty($fs['plz']) &&
            !empty($fs['lat']) &&
            !empty($fs['lon']) &&
            !empty($fs['photo']);
    }

    /**
     * Checks if the foodsaver has a home region.
     *
     * @param int $fsId the ID of the foodsaver
     * @return bool true if the foodsaver has a home region, false otherwise
     */
    public function hasHomeRegion(int $fsId): bool
    {
        return $this->getHomeRegionOfFoodsaver($fsId) > 0;
    }

    /**
     * Checks if the foodsaver has a phone number.
     *
     * This method verifies if the foodsaver identified by the given ID has either a mobile phone number (handy)
     * or a landline phone number (telefon) that is not null and not an empty string.
     *
     * @param int $fsId the ID of the foodsaver
     * @return bool true if the foodsaver has a phone number, false otherwise
     */
    public function hasPhone(int $fsId): bool
    {
        return $this->db->fetch('
                SELECT COUNT(*) as count
                FROM `fs_foodsaver`
                WHERE
                    id = :fsId
                    AND ((handy IS NOT NULL AND handy != \'\')
                        OR (telefon IS NOT NULL AND telefon != \'\'))
        ', [
            ':fsId' => $fsId,
        ])['count'] > 0;
    }

    public function getFoodsaversWithoutAmbassadors(): array
    {
        $foodsavers = $this->getActiveFoodsavers();
        $ambassadors = $this->getActiveAmbassadors();

        return array_udiff($foodsavers, $ambassadors, fn (array $fs, array $amb) => $fs['id'] - $amb['id']);
    }

    private function getActiveFoodsavers(): array
    {
        return $this->db->fetchAll('
			SELECT  fs.id,
					CONCAT(fs.`name`, " ", fs.`nachname`) AS `name`,
					fs.`anschrift`,
					fs.`email`,
					fs.`telefon`,
					fs.`handy`,
					fs.plz

			FROM 	`fs_foodsaver` fs

			WHERE	fs.deleted_at IS NULL
            AND     fs.`active` = 1
		');
    }

    public function getFoodsaver(int $fsId): array
    {
        $out = $this->db->fetchByCriteria('fs_foodsaver', [
            'id',
            'bezirk_id',
            'plz',
            'stadt',
            'lat',
            'lon',
            'email',
            'name',
            'nachname',
            'anschrift',
            'telefon',
            'handy',
            'geschlecht',
            'geb_datum',
            'anmeldedatum',
            'photo',
            'about_me_intern',
            'about_me_public',
            'rolle',
            'position',
            'no_automatic_delete',
            'is_sleeping'
        ], [
            'id' => $fsId
        ]);

        if ($bot = $this->getAmbassadorsRegions($fsId)) {
            $out['botschafter'] = $bot;
        }

        return $out;
    }

    public function getAmbassadorsRegions(int $fsId, bool $accessibleRegionsOnly = false): array
    {
        $regionFilter = '';
        if ($accessibleRegionsOnly) {
            $regionFilter = 'AND reg.type IN(' . $this->dataHelper->commaSeparatedIds(UnitType::getAccessibleRegionTypes()) . ')';
        }

        return $this->db->fetchAll("SELECT
                reg.`name`, reg.`id`
			FROM fs_bezirk reg
		    INNER JOIN fs_botschafter amb ON amb.`bezirk_id` = reg.`id`
			WHERE    amb.foodsaver_id = :fsId
            {$regionFilter}", [
            ':fsId' => $fsId
        ]);
    }

    public function getAdminsOrAmbassadors(int $groupId): array
    {
        return $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`name` AS `vorname`,
					fs.`nachname`,
					fs.`photo`,
					fs.`email`,
					fs.`geschlecht`,
					fs.`is_sleeping`

			FROM    `fs_foodsaver` fs
			        INNER JOIN `fs_botschafter` amb
                    ON fs.id = amb.`foodsaver_id`

			WHERE amb.`bezirk_id` = :regionId
			AND		fs.deleted_at IS NULL',
            [':regionId' => $groupId]
        );
    }

    public function getActiveAmbassadors(): array
    {
        return $this->db->fetchAll('
			SELECT  fs.`id`,
					fs.`name`,
					fs.`nachname`,
					fs.`geschlecht`,
					fs.`email`

			FROM 	`fs_foodsaver` fs
                    JOIN `fs_botschafter` amb
                    ON fs.id = amb.foodsaver_id
                        LEFT JOIN `fs_bezirk` reg
                        ON amb.bezirk_id = reg.id

			WHERE	reg.type != :excludedRegionType
			AND     fs.deleted_at IS NULL
            AND     fs.`active` = 1
        ', [
            ':excludedRegionType' => UnitType::WORKING_GROUP
        ]);
    }

    public function getOrgaTeam(): array
    {
        return $this->db->fetchAllByCriteria('fs_foodsaver', [
            'id',
            'name',
            'nachname',
            'geschlecht',
            'email'
        ], [
            'rolle' => Role::ORGA->value
        ]);
    }

    public function getOrgaTeamId(): array
    {
        return $this->db->fetchAllByCriteria('fs_foodsaver', [
            'id'
        ], [
            'rolle' => Role::ORGA->value
        ]);
    }

    public function getEmailAddress(int $fsId): string
    {
        return $this->db->fetchValueByCriteria('fs_foodsaver', 'email', ['id' => $fsId]);
    }

    public function getEmailAddressesFromRegions(array $regionIds): array
    {
        $foodsavers = $this->db->fetchAll('
			SELECT 	fs.`id`,
					fs.`email`

			FROM 	`fs_foodsaver` fs
					INNER JOIN `fs_foodsaver_has_bezirk` b
					ON b.foodsaver_id = fs.id

			WHERE 	fs.deleted_at IS NULL
			AND     b.`bezirk_id` > 0
			AND     b.`bezirk_id` IN(' . $this->dataHelper->commaSeparatedIds($regionIds) . ')
		');

        return $this->dataHelper->useIdAsKey($foodsavers);
    }

    public function updateGroupMembers(int $regionId, array $fsIds, bool $keepAdmins): array
    {
        if ($keepAdmins) {
            if ($admins = $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $regionId])) {
                $fsIds = array_merge($fsIds, $admins);
            }
        }

        $updateCounts = ['inserts' => 0, 'deletions' => 0];
        if ($fsIds) {
            $updateCounts['deletions'] = $this->deleteGroupMembers($regionId, $fsIds);
            $updateCounts['inserts'] = $this->insertGroupMembers($regionId, $fsIds);
        } else {
            $updateCounts['deletions'] = $this->deleteGroupMembers($regionId);
        }

        return $updateCounts;
    }

    private function deleteGroupMembers(int $regionId, array $remainingMemberIds = []): int
    {
        $this->forumFollowerGateway->deleteForumSubscriptions($regionId, $remainingMemberIds, false);

        if ($remainingMemberIds) {
            $delCount = 0;
            $preGroupMembers = $this->db->fetchAllValuesByCriteria('fs_foodsaver_has_bezirk', 'foodsaver_id', [
                'bezirk_id' => $regionId
            ]);
            foreach ($preGroupMembers as $fsId) {
                if (!in_array($fsId, $remainingMemberIds)) {
                    $delCount += $this->db->delete(
                        'fs_foodsaver_has_bezirk',
                        ['bezirk_id' => $regionId, 'foodsaver_id' => $fsId]
                    );
                }
            }

            return $delCount;
        }

        return $this->db->delete('fs_foodsaver_has_bezirk', ['bezirk_id' => $regionId]);
    }

    private function insertGroupMembers(int $regionId, array $fsIds): int
    {
        $before = $this->db->count('fs_foodsaver_has_bezirk', ['bezirk_id' => $regionId]);
        foreach ($fsIds as $fsId) {
            $this->db->insertIgnore(
                'fs_foodsaver_has_bezirk',
                [
                    'foodsaver_id' => $fsId,
                    'bezirk_id' => $regionId,
                    'active' => 1,
                    'added' => $this->db->now()
                ]
            );
        }
        $current = $this->db->count('fs_foodsaver_has_bezirk', ['bezirk_id' => $regionId]);

        return $current - $before;
    }

    public function getRegionAmbassadorIds(int $regionId): array
    {
        return $this->getAmbassadorIds($regionId);
    }

    /**
     * Retrieves the list of all ambassador for a given region or district.
     *
     * Because the region data model holds both, <i>regions</i> <b>and</b> <i>work groups</i>,
     * one can decide which one to query via flag parameters.
     *
     * @param int $regionId The region ID
     * @param bool $includeRegionAmbassador "Real" regions shall be queried
     * @param bool $includeGroupAmbassador Work groups shall be queried. If <code>$includeRegionAmbassador</code> is <code>false</code>,
     *     this is implicitely handled as <code>true</code>.
     */
    private function getAmbassadorIds(int $regionId, bool $includeRegionAmbassador = true, bool $includeGroupAmbassador = false): array
    {
        $sql = '
			SELECT DISTINCT
					amb.foodsaver_id

			FROM	`fs_bezirk_closure` rc
					LEFT JOIN `fs_bezirk` reg
					ON rc.bezirk_id = reg.id
						INNER JOIN `fs_botschafter` amb
						ON rc.bezirk_id = amb.bezirk_id
							INNER JOIN `fs_foodsaver` fs
							ON amb.foodsaver_id = fs.id

			WHERE  (rc.ancestor_id = :ancestorId
                    OR rc.bezirk_id = :regionId)
			AND		fs.deleted_at IS NULL
		';

        if (!$includeRegionAmbassador) {
            $sql .= ' AND reg.type = ' . UnitType::WORKING_GROUP;
        } elseif (!$includeGroupAmbassador) {
            $sql .= ' AND reg.type != ' . UnitType::WORKING_GROUP;
        }

        return $this->db->fetchAllValues(
            $sql, [
            ':ancestorId' => $regionId,
            ':regionId' => $regionId
        ]);
    }

    /**
     * Retrieves the list of all admins for all existing workgroup function.
     *
     * Because the region data model holds both, <i>regions</i> <b>and</b> <i>work groups</i>,
     * one can decide which one to query via flag parameters.
     *
     * Testdistricts are excluded (343 Streuobstwiese,3113 Apfelbaum)
     *
     * @param int $wgFunction The workgroup function ID
     */
    public function getWorkgroupFunctionAdminIds(int $wgFunction): array
    {
        $sql = '
			select
			    distinct b.foodsaver_id
			from fs_botschafter b
				left outer join fs_region_function rf on b.bezirk_id = rf.region_id
			where
				rf.function_id = :wgFunctionId
			and rf.target_id not in (:excludedRegions)
		';

        return $this->db->fetchAllValues(
            $sql, [
            ':wgFunctionId' => $wgFunction,
            ':excludedRegions' => join(',', RegionIDs::getTestRegions())
        ]);
    }

    /**
     * This function should not be used directly. Use the function in FoodsaverTransaction instead, which cleans up
     * additional data.
     */
    public function deleteFoodsaver(int $fsId, ?int $deletingUser, ?string $reason): void
    {
        $this->db->update('fs_foodsaver', [
            'password' => null,
            'deleted_at' => $this->db->now(),
            'totp_secret' => null,
            'backup_codes' => null,
        ], ['id' => $fsId]);

        $this->archiveFoodsaver($fsId);

        $this->db->delete('fs_apitoken', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_basket_anfrage', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_botschafter', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_buddy', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_buddy', ['buddy_id' => $fsId]);
        $this->db->delete('fs_fairteiler_follower', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_bell', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_bezirk', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_contact', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_event', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_wallpost', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_mailbox_member', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_mailchange', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_pass_gen', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_pass_gen', ['bot_id' => $fsId]);
        $this->db->delete('fs_pass_request', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_quiz_session', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_rating', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_theme_follower', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_resource', ['foodsaver_id' => $fsId]);

        $this->db->update(
            'fs_foodsaver',
            [
                'verified' => 0,
                'rolle' => 0,
                'plz' => null,
                'stadt' => null,
                'lat' => null,
                'lon' => null,
                'photo' => null,
                'email' => null,
                'password' => null,
                'totp_secret' => null,
                'backup_codes' => null,
                'name' => null,
                'nachname' => null,
                'anschrift' => null,
                'telefon' => null,
                'handy' => null,
                'geb_datum' => null,
                'deleted_at' => $this->db->now(),
                'deleted_by' => $deletingUser,
                'deleted_reason' => $reason
            ], [
            'id' => $fsId
        ]);
    }

    private function archiveFoodsaver(int $fsId): void
    {
        $foodsaver = $this->db->fetchByCriteria('fs_foodsaver', '*', [
            'id' => $fsId
        ]);
        unset($foodsaver['is_sleeping']); // dont archive computed column

        if (!is_null($foodsaver['name'])) {
            $this->db->insertOrUpdate('fs_foodsaver_archive', $foodsaver);
        }
    }

    /**
     * @deprecated
     */
    public function updateProfile(int $fsId, array $data): bool
    {
        $fields = [
            'bezirk_id',
            'plz',
            'lat',
            'lon',
            'stadt',
            'anschrift',
            'telefon',
            'handy',
            'geb_datum',
            'about_me_intern',
            'about_me_public',
            'position',
            'no_automatic_delete'
        ];

        $fieldsToStripTags = [
            'plz',
            'lat',
            'lon',
            'stadt',
            'anschrift',
            'telefon',
            'handy',
            'about_me_intern',
            'about_me_public',
            'position'
        ];

        $clean_data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $clean_data[$field] = in_array($field, $fieldsToStripTags, true) ? strip_tags((string)$data[$field]) : $data[$field];
            }
        }

        $this->db->update('fs_foodsaver', $clean_data, [
            'id' => $fsId
        ]);

        return true;
    }

    public function updatePhoto(int $fsId, string $photo): void
    {
        $this->db->update('fs_foodsaver', [
            'photo' => strip_tags($photo)
        ], [
            'id' => $fsId
        ]);
    }

    public function getPhotoFileName(int $fsId): string
    {
        if ($photo = $this->db->fetchValueByCriteria('fs_foodsaver', 'photo', ['id' => $fsId])) {
            return $photo;
        }

        return '';
    }

    public function emailExists(string $email): bool
    {
        return $this->db->exists('fs_foodsaver', ['email' => $email]);
    }

    /**
     * @throws Exception
     */
    public function emailDomainIsBlacklisted(string $email): bool
    {
        $emailDomain = strtolower(explode('@', $email)[1]);

        return $this->db->exists('fs_email_blacklist', ['email' => $emailDomain]);
    }

    /**
     * 	Deletes the foodsaver from a region.
     *  If the foodsaver is also the actor and removes himself from his home region
     *  the verification is removed.
     *
     * @param int $regionId regionId that foodsaverid is being deleted from
     * @param int|null $fsId foodsaverid that is being deleted from a region
     * @param int $actorId foodsaverid that is performing the action (either self or ambassador)
     * @return bool true if the foodsaver removed himself from his home region and got unverified
     *
     * @throws Exception
     */
    public function deleteFromRegion(int $regionId, ?int $fsId, int $actorId): bool
    {
        if ($fsId === null) {
            return false;
        }
        $this->db->delete('fs_botschafter', ['bezirk_id' => $regionId, 'foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_bezirk', ['bezirk_id' => $regionId, 'foodsaver_id' => $fsId]);

        $this->forumFollowerGateway->deleteForumSubscription($regionId, $fsId);

        // Revoke OAuth refresh tokens to force fresh region claims on next refresh
        $this->revokeOAuthRefreshTokens($fsId);

        $mainRegion_id = $this->db->fetchValueByCriteria('fs_foodsaver', 'bezirk_id', ['id' => $fsId]);
        if ($mainRegion_id === $regionId) {
            $this->db->update('fs_foodsaver', [
                'bezirk_id' => 0,
            ], [
                'id' => $fsId
            ]);
            $this->db->insert(
                'fs_foodsaver_change_history',
                [
                    'date' => $this->db->now(),
                    'fs_id' => $fsId,
                    'changer_id' => $actorId,
                    'object_name' => 'bezirk_id',
                    'old_value' => $mainRegion_id,
                    'new_value' => 0
                ]
            );
            if ($fsId === $actorId) {
                $this->changeUserVerification($fsId, $actorId, false);

                return true;
            }
        }

        return false;
    }

    /**
     * Revokes all OAuth refresh tokens for a user.
     * This forces the user to get fresh access tokens with updated claims (e.g., regions)
     * on their next token refresh. Access tokens remain valid until expiration (1 hour).
     */
    public function revokeOAuthRefreshTokens(int $userId): void
    {
        $this->db->execute(
            '
            UPDATE oauth_refresh_tokens rt
            INNER JOIN oauth_access_tokens at ON rt.access_token_identifier = at.identifier
            SET rt.revoked = 1
            WHERE rt.revoked = 0
              AND at.user_identifier = :userId
            ',
            [':userId' => (string)$userId]
        );
    }

    public function riseRole(int $fsId, Role $newRoleId): void
    {
        $this->db->update(
            'fs_foodsaver',
            ['rolle' => $newRoleId->value],
            [
                'id' => $fsId,
                'rolle <' => $newRoleId->value
            ]
        );
    }

    public function riseQuizRole(int $fsId, Role $newRoleId): void
    {
        $this->db->update(
            'fs_foodsaver',
            ['quiz_rolle' => $newRoleId->value],
            [
                'id' => $fsId,
                'quiz_rolle <' => $newRoleId->value
            ]
        );
    }

    public function updateFoodsaver(int $fsId, EditableProfileDTO $editableProfileDTO): int
    {
        // This is necessary because trimming null returns an empty string
        $trimIfNotNull = fn ($value) => is_null($value) ? null : strip_tags(trim((string)$value));

        $updateData = [
            'bezirk_id' => $editableProfileDTO->regionId,
            'plz' => $trimIfNotNull($editableProfileDTO->location?->postalCode),
            'stadt' => $trimIfNotNull($editableProfileDTO->location?->city),
            'lat' => $editableProfileDTO->coordinate ? (string)$editableProfileDTO->coordinate->lat : null,
            'lon' => $editableProfileDTO->coordinate ? (string)$editableProfileDTO->coordinate->lon : null,
            'name' => $trimIfNotNull($editableProfileDTO->firstName),
            'nachname' => $trimIfNotNull($editableProfileDTO->lastName),
            'anschrift' => $trimIfNotNull($editableProfileDTO->location?->street),
            'telefon' => $trimIfNotNull($editableProfileDTO->phone),
            'handy' => $trimIfNotNull($editableProfileDTO->mobile),
            'geschlecht' => $editableProfileDTO->gender,
            'geb_datum' => $trimIfNotNull($editableProfileDTO->birthday),
            'position' => $trimIfNotNull($editableProfileDTO->position),
            'no_automatic_delete' => $editableProfileDTO->noAutoDelete,
            'rolle' => $editableProfileDTO->role,
            'about_me_intern' => $trimIfNotNull($editableProfileDTO->aboutMeInternal),
            'about_me_public' => $trimIfNotNull($editableProfileDTO->aboutMePublic),
        ];
        // Only use fields with a non-null value because a null value means that the field should not be updated
        $updateData = array_filter($updateData, fn ($var) => $var !== null);

        return $this->db->update('fs_foodsaver', $updateData, [
            'id' => $fsId
        ]);
    }

    public function downgradePermanently(int $fsId): int
    {
        $this->db->delete('fs_foodsaver_has_bell', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_foodsaver_has_bezirk', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_botschafter', ['foodsaver_id' => $fsId]);
        $this->db->delete('fs_theme_follower', ['foodsaver_id' => $fsId]);

        $fsUpdateData['rolle'] = Role::FOODSHARER->value;
        $fsUpdateData['bezirk_id'] = 0;
        $fsUpdateData['quiz_rolle'] = Role::FOODSHARER->value;
        $fsUpdateData['verified'] = 0;

        return $this->db->update('fs_foodsaver', $fsUpdateData, [
            'id' => $fsId
        ]);
    }

    public function getFoodsaverAddress(int $foodsaverId): array
    {
        return $this->db->fetchByCriteria(
            'fs_foodsaver',
            [
                'plz',
                'stadt',
                'lat',
                'lon',
                'anschrift',
            ],
            ['id' => $foodsaverId]
        );
    }

    public function getRole(int $fsId): ?Role
    {
        $role = $this->db->fetchValueByCriteria('fs_foodsaver', 'rolle', ['id' => $fsId]);

        return Role::tryFrom($role);
    }

    public function getQuizRole(int $fsId): ?Role
    {
        $quizRole = $this->db->fetchValueByCriteria('fs_foodsaver', 'quiz_rolle', ['id' => $fsId]);

        return Role::tryFrom($quizRole);
    }

    public function getSubscriptions(int $fsId): array
    {
        return $this->db->fetchByCriteria(
            'fs_foodsaver',
            [
                'infomail_message',
            ],
            ['id' => $fsId]
        );
    }

    /**
     * Returns the minimal profile data of one user.
     *
     * @param int $userId id of the foodsharer
     *
     * @return ?Profile the user's profile or null if the user id does not exist
     */
    public function getProfile(int $userId): ?Profile
    {
        $data = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['id', 'name', 'photo', 'is_sleeping'],
            ['id' => $userId]);

        return empty($data) ? null : new Profile($data);
    }

    /**
     * Get foodsaver by email address.
     *
     * @return array|null the user data or null if not found
     */
    public function getFoodsaverByEmail(string $email): ?array
    {
        $data = $this->db->fetchByCriteria(
            'fs_foodsaver',
            ['id', 'name', 'nachname', 'geschlecht'],
            ['email' => trim($email)]
        );

        return $data ?: null;
    }

    /**
     * Returns the minimal profile data of multiple users.
     *
     * @param int[] $fsIds ids of the foodsavers
     *
     * @return Profile[]
     */
    public function getProfileForUsers(array $fsIds): array
    {
        $users = $this->db->fetchAllByCriteria(
            'fs_foodsaver',
            ['id', 'name', 'photo', 'is_sleeping'],
            ['id' => $fsIds]);

        return array_map(fn ($user) => new Profile($user), $users);
    }

    /**
     * Returns the first name of the foodsaver.
     */
    public function getFoodsaverName($foodsaverId): string
    {
        return $this->db->fetchValueByCriteria('fs_foodsaver', 'name', ['id' => $foodsaverId, 'deleted_at' => null]);
    }

    public function foodsaverExists($foodsaverId): bool
    {
        return $this->foodsaversExist([$foodsaverId]);
    }

    public function foodsaversExist(array $foodsaverIds): bool
    {
        $foodsaverIds = array_unique($foodsaverIds);
        $existing = $this->db->fetchAllValuesByCriteria('fs_foodsaver', 'id', ['id' => $foodsaverIds, 'deleted_at' => null]);

        return count($foodsaverIds) === count($existing);
    }

    public function changeUserVerification(int $userId, int $actorId, bool $newStatus): void
    {
        $updated = $this->db->update('fs_foodsaver', ['verified' => intval($newStatus)], [
            'id' => $userId,
        ]);

        if ($updated > 0) {
            $verificationChange = [
                'fs_id' => $userId,
                'date' => $this->db->now(),
                'bot_id' => $actorId,
                'change_status' => intval($newStatus),
            ];

            $this->db->insert('fs_verify_history', $verificationChange);
        }
    }

    public function foodsaverWasVerifiedBefore(int $userId): bool
    {
        return $this->db->exists('fs_verify_history', ['fs_id' => $userId]);
    }

    /**
     * Tests whether one user applied to any store of another user.
     *
     * @param int $userId User id of the possible applicant
     * @param int $storemanagerId User id of storemanager for whose ablicants to check
     *
     * @return bool true if the user with id userId is an applicant to any of the stores managed by the user with id storemanagerId, false otherwise
     */
    public function isApplicant(int $userId, int $storemanagerId): bool
    {
        $applications = $this->db->fetchAll('
			(SELECT betrieb_id from fs_betrieb_team WHERE :fsId = foodsaver_id AND active = 0)
			INTERSECT
			(SELECT betrieb_id from fs_betrieb_team WHERE :storemanagerId = foodsaver_id AND verantwortlich = 1);
		', [
            ':fsId' => $userId,
            ':storemanagerId' => $storemanagerId
        ]);

        return !empty($applications);
    }

    /**
     * @return int[]
     */
    public function listInactiveUsers(): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_foodsaver', 'id', ['last_login <=' => Carbon::now()->subYears(5)->toDateString(), 'deleted_at' => null, 'no_automatic_delete' => 0]);
    }

    public function getUserNames(array $userIds): array
    {
        return $this->db->fetchAllByCriteria('fs_foodsaver', ['id', 'name'], ['id' => $userIds, 'deleted_at' => null]);
    }

    public function getUserMarkers(int $regionId, UserMarkerRoleType $role, UserMarkerActivityType $activity, UserMarkerMemberType $member): array
    {
        $query = 'SELECT
            fs.id, fs.lat, fs.lon, fs.name
        FROM fs_foodsaver fs
        JOIN fs_foodsaver_has_bezirk r ON r.foodsaver_id = fs.id';
        $conditions = [
            'fs.lat != ""',
            'fs.lon != ""',
            'fs.deleted_at IS NULL',
            'r.bezirk_id = :regionId',
        ];
        $params[':regionId'] = $regionId;

        if ($role !== UserMarkerRoleType::ALL) {
            $conditions[] = 'fs.rolle >= :role';
            $conditions[] = 'fs.verified = 1';
            $roleValue = match ($role) {
                UserMarkerRoleType::FOODSAVER => Role::FOODSAVER->value,
                UserMarkerRoleType::STORE_MANAGER => Role::STORE_MANAGER->value,
            };
            $params[':role'] = $roleValue;
        }

        if ($activity !== UserMarkerActivityType::ALL) {
            $conditions[] = 'fs.last_login >= :activityDate';
            $activityDate = Carbon::now();
            $activityDate->sub(match ($activity) {
                UserMarkerActivityType::WEEK => new DateInterval('P1W'),
                UserMarkerActivityType::MONTH => new DateInterval('P1M'),
                UserMarkerActivityType::THREE_MONTHS => new DateInterval('P3M'),
                UserMarkerActivityType::SIX_MONTHS => new DateInterval('P6M'),
            });
            $params[':activityDate'] = $activityDate;
        }

        if ($member !== UserMarkerMemberType::ALL) {
            $operator = $member === UserMarkerMemberType::HOMEREGION ? '=' : '!=';
            $conditions[] = "fs.bezirk_id {$operator} :homeRegionId";
            $params[':homeRegionId'] = $regionId;
        }

        $query .= ' WHERE ' . implode(' AND ', $conditions);
        $markers = $this->db->fetchAll($query, $params);

        return array_map(fn ($marker) => MapMarker::create($marker['id'], $marker['name'], $marker['lat'], $marker['lon']), $markers);
    }

    public function setPersonalMailboxId(int $userId, int $mailboxId): void
    {
        $this->db->update('fs_foodsaver', ['mailbox_id' => $mailboxId], ['id' => $userId]);
    }
}
