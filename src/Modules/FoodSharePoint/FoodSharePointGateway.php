<?php

namespace Foodsharing\Modules\FoodSharePoint;

use DateTime;
use Exception;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\ActivationStatus;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\FoodSharePoint\DTO\FoodSharePoint;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Map\DTO\MapMarker;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\RestApi\DTO\Notifications\NotificationSetting;
use Foodsharing\RestApi\DTO\Notifications\NotificationSettingPatch;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForListView;

class FoodSharePointGateway extends BaseGateway
{
    private readonly RegionGateway $regionGateway;
    private readonly BellGateway $bellGateway;
    private readonly GroupFunctionGateway $groupFunctionGateway;

    public function __construct(
        Database $db,
        RegionGateway $regionGateway,
        BellGateway $bellGateway,
        GroupFunctionGateway $groupFunctionGateway
    ) {
        parent::__construct($db);
        $this->regionGateway = $regionGateway;
        $this->bellGateway = $bellGateway;
        $this->groupFunctionGateway = $groupFunctionGateway;
    }

    public function getEmailFollower(int $id): array
    {
        return $this->db->fetchAll(
            '
			SELECT 	fs.`id`,
					fs.`name`,
					fs.`nachname`,
					fs.`email`,
					fs.`geschlecht`

			FROM 	`fs_fairteiler_follower` ff,
					`fs_foodsaver` fs

			WHERE 	ff.foodsaver_id = fs.id
			AND 	ff.fairteiler_id = :id
			AND 	ff.infotype = :infoType
		',
            [':id' => $id, ':infoType' => InfoType::EMAIL]
        );
    }

    public function getLastFoodSharePointPost(int $foodSharePointId): array
    {
        return $this->db->fetch(
            '
			SELECT 		wp.id,
						wp.time,
						UNIX_TIMESTAMP(wp.time) AS time_ts,
						wp.body,
						wp.attach,
						fs.name AS fs_name,
						fs.id AS fs_id

			FROM 		fs_fairteiler_has_wallpost hw
			LEFT JOIN 	fs_wallpost wp
			ON 			hw.wallpost_id = wp.id

			LEFT JOIN 	fs_foodsaver fs ON wp.foodsaver_id = fs.id

			WHERE 		hw.fairteiler_id = :foodSharePointId

			ORDER BY 	wp.id DESC
			LIMIT 1
		',
            [':foodSharePointId' => $foodSharePointId]
        );
    }

    public function updateFSPManagers(int $foodSharePointId, array $fspManagers): void
    {
        $values = [];

        foreach ($fspManagers as $fs) {
            $values[] = [
                'fairteiler_id' => $foodSharePointId,
                'foodsaver_id' => (int)$fs,
                'type' => FollowerType::FOOD_SHARE_POINT_MANAGER,
                'infotype' => InfoType::EMAIL
            ];
        }

        $this->db->update(
            'fs_fairteiler_follower',
            ['type' => FollowerType::FOLLOWER],
            ['fairteiler_id' => $foodSharePointId]
        );

        $this->db->insertOrUpdateMultiple('fs_fairteiler_follower', $values);
    }

    public function getInfoFollowerIds(int $foodSharePointId): array
    {
        return $this->db->fetchAllValues(
            '
			SELECT 	fs.`id`

			FROM 	`fs_fairteiler_follower` ff,
					`fs_foodsaver` fs

			WHERE 	ff.foodsaver_id = fs.id
			AND 	ff.fairteiler_id = :foodSharePointId
		',
            [':foodSharePointId' => $foodSharePointId]
        );
    }

    /**
     * @return FoodSharePointForListView[]
     */
    public function listActiveFoodSharePoints(array $regionIds): array
    {
        if (!$regionIds) {
            return [];
        }
        $foodSharePoints = $this->db->fetchAll('SELECT
                `id`,
                `name`,
                `picture`
			FROM `fs_fairteiler`
			WHERE `bezirk_id` IN( ' . implode(',', $regionIds) . ' )
			    AND	`status` = :activeStatus
			ORDER BY `name`',
            [':activeStatus' => ActivationStatus::ACTIVE],
        );

        return array_map(fn ($fsp) => FoodSharePointForListView::create(
            $fsp['id'], $fsp['name'], $fsp['picture']
        ), $foodSharePoints);
    }

    /**
     * @return MapMarker[]
     */
    public function getFoodSharePointsForRegion(int $regionId): array
    {
        $foodSharePoints = $this->db->fetchAllByCriteria('fs_fairteiler', ['id', 'name', 'lat', 'lon'], [
            'bezirk_id' => $regionId,
            'status' => 1
        ]);

        return array_map(function ($foodSharePoint) {
            return MapMarker::create(
                $foodSharePoint['id'],
                $foodSharePoint['name'],
                $foodSharePoint['lat'],
                $foodSharePoint['lon']
            );
        }, $foodSharePoints);
    }

    /** @return NotificationSetting[] */
    public function getFoodSharePointsNotificationSettings(int $fsId): array
    {
        $subsciptions = $this->db->fetchAll('SELECT
				ft.id, ft.name, ff.infotype
			FROM `fs_fairteiler_follower` ff
            LEFT JOIN `fs_fairteiler` ft ON ff.fairteiler_id = ft.id
			WHERE ff.foodsaver_id = :userId
		', [':userId' => $fsId]);

        return array_map(fn ($subscription) => new NotificationSetting(
            $subscription['id'], $subscription['name'],
            //InfoType::EMAIL is interpreted as bell AND email for food share points
            $subscription['infotype'] === InfoType::EMAIL || $subscription['infotype'] === InfoType::BELL,
            $subscription['infotype'] === InfoType::EMAIL
        ), $subsciptions);
    }

    public function follow(int $foodsaverId, int $foodSharePointId, int $infoType): void
    {
        $this->db->insertIgnore(
            'fs_fairteiler_follower',
            [
                'fairteiler_id' => $foodSharePointId,
                'foodsaver_id' => $foodsaverId,
                'type' => FollowerType::FOLLOWER,
                'infotype' => $infoType,
            ]
        );
    }

    public function unfollow(int $fsId, int $foodSharePointId): int
    {
        return $this->db->delete(
            'fs_fairteiler_follower',
            [
                'fairteiler_id' => $foodSharePointId,
                'foodsaver_id' => $fsId
            ]
        );
    }

    /**
     * @param NotificationSettingPatch[] $changes
     */
    public function updateFoodSharePointsNotifications(int $userId, array $changes): void
    {
        $valuesToUpdate = [];
        $idsToRemove = [];
        foreach ($changes as $change) {
            $update = ['foodsaver_id' => $userId, 'fairteiler_id' => $change->id];
            if ($change->email) {
                $update['infotype'] = InfoType::EMAIL;
            } elseif ($change->bell) {
                $update['infotype'] = InfoType::BELL;
            } else {
                $idsToRemove[] = $change->id;
                continue;
            }
            $valuesToUpdate[] = $update;
        }
        if (count($valuesToUpdate)) {
            $this->db->insertOrUpdateMultiple('fs_fairteiler_follower', $valuesToUpdate);
        }
        if (count($idsToRemove)) {
            $this->db->delete('fs_fairteiler_follower', ['foodsaver_id' => $userId, 'fairteiler_id' => $idsToRemove]);
        }
    }

    public function acceptFoodSharePoint(int $foodSharePointId): void
    {
        $this->db->update('fs_fairteiler', ['status' => 1], ['id' => $foodSharePointId]);
        $this->removeBellNotificationForNewFoodSharePoint($foodSharePointId);
    }

    public function foodSharePointExists(int $foodSharePointId, bool $includeUnconfirmed = true): bool
    {
        $criteria = ['id' => $foodSharePointId];
        if (!$includeUnconfirmed) {
            $criteria['status'] = 1;
        }

        return $this->db->exists('fs_fairteiler', $criteria);
    }

    public function updateFoodSharePoint(int $foodSharePointId, FoodSharePointEditData $foodSharePointData): bool
    {
        $this->db->requireExists('fs_fairteiler', ['id' => $foodSharePointId]);
        $this->db->update('fs_fairteiler', [
            'name' => $foodSharePointData->name,
            'desc' => $foodSharePointData->description,
            'anschrift' => strip_tags($foodSharePointData->address),
            'plz' => preg_replace('[^0-9]', '', $foodSharePointData->postalCode),
            'ort' => strip_tags($foodSharePointData->city),
            'picture' => $foodSharePointData->picture ?? '',
            'bezirk_id' => $foodSharePointData->regionId,
            'lat' => $foodSharePointData->location->lat,
            'lon' => $foodSharePointData->location->lon,
        ], ['id' => $foodSharePointId]);

        return true;
    }

    public function deleteFoodSharePoint(int $foodSharePointId): int
    {
        $this->db->delete('fs_fairteiler_follower', ['fairteiler_id' => $foodSharePointId]);

        $result = $this->db->delete('fs_fairteiler', ['id' => $foodSharePointId]);

        $this->removeBellNotificationForNewFoodSharePoint($foodSharePointId);

        return $result;
    }

    public function getFoodSharePoint(int $foodSharePointId): ?FoodSharePoint
    {
        $foodSharePoint = $this->db->fetch('SELECT
                ft.`id`,
                ft.`bezirk_id`,
                ft.`name`,
                ft.`picture`,
                ft.`status`,
                ft.`desc`,
                ft.`anschrift` as street,
                ft.`plz` as postalCode,
                ft.`ort` as city,
                ft.`lat`,
                ft.`lon`,
                ft.`add_date`,
                ft.`add_foodsaver`,
                fs.`name` AS fs_name,
                fs.`id` AS fs_id,
                fs.`is_sleeping` AS fs_is_sleeping,
                fs.`photo` AS fs_avatar
			FROM  fs_fairteiler ft
			LEFT JOIN fs_foodsaver fs ON ft.`add_foodsaver` = fs.`id`
			WHERE  ft.`id` = :foodSharePointId',
            [':foodSharePointId' => $foodSharePointId]
        );
        if (!$foodSharePoint) {
            return null;
        }

        return FoodSharePoint::create(
            $foodSharePoint['id'],
            $foodSharePoint['name'],
            $foodSharePoint['bezirk_id'],
            $foodSharePoint['picture'],
            ActivationStatus::tryFrom($foodSharePoint['status']),
            $foodSharePoint['desc'],
            Address::createFromArray($foodSharePoint),
            new GeoLocation(floatval($foodSharePoint['lat']), floatval($foodSharePoint['lon'])),
            DateTime::createFromFormat('Y-m-d', $foodSharePoint['add_date']),
            new Profile($foodSharePoint['fs_id'], $foodSharePoint['fs_name'], $foodSharePoint['fs_avatar'], (bool)$foodSharePoint['fs_is_sleeping'])
        );
    }

    public function getFollowerCount(int $foodSharePointId): int
    {
        return $this->db->fetchValue('SELECT COUNT(*)
            FROM fs_fairteiler_follower
            WHERE `fairteiler_id` = :foodSharePointId
                AND `type` = :followerType
        ', [
            ':foodSharePointId' => $foodSharePointId,
            ':followerType' => FollowerType::FOLLOWER,
        ]);
    }

    /**
     * @return Profile[]
     */
    public function getManagers(int $foodSharePointId): array
    {
        $managers = $this->db->fetchAll('SELECT
                fs.id, fs.name, fs.photo, fs.is_sleeping
            FROM fs_fairteiler_follower ff
            JOIN fs_foodsaver fs ON fs.id = ff.foodsaver_id
            WHERE ff.fairteiler_id = :foodSharePointId
                AND ff.type = :managerType
                AND fs.deleted_at IS NULL
        ', [
            ':foodSharePointId' => $foodSharePointId,
            ':managerType' => FollowerType::FOOD_SHARE_POINT_MANAGER,
        ]);

        return array_map(fn ($manager) => new Profile(
            $manager['id'], $manager['name'], $manager['photo'], (bool)$manager['is_sleeping']
        ), $managers);
    }

    public function addFoodSharePoint(int $foodsaverId, FoodSharePointForCreation $data, bool $isProposal): int
    {
        $food_share_point_id = $this->db->insert('fs_fairteiler', [
            'bezirk_id' => $data->regionId,
            'name' => $data->name,
            'picture' => $data->picture ?? '',
            'desc' => $data->description,
            'anschrift' => strip_tags($data->address),
            'plz' => preg_replace('[^0-9]', '', $data->postalCode),
            'ort' => strip_tags($data->city),
            'lon' => $data->location->lon,
            'lat' => $data->location->lat,
            'status' => $isProposal ? 0 : 1,
            'add_date' => date('Y-m-d H:i:s'),
            'add_foodsaver' => $foodsaverId,
        ]);
        if ($food_share_point_id) {
            $this->db->insert(
                'fs_fairteiler_follower',
                ['fairteiler_id' => $food_share_point_id, 'foodsaver_id' => $foodsaverId, 'type' => FollowerType::FOOD_SHARE_POINT_MANAGER]
            );

            $this->sendBellNotificationForNewFoodSharePoint($food_share_point_id);
        }

        return $food_share_point_id;
    }

    private function sendBellNotificationForNewFoodSharePoint(int $foodSharePointId): void
    {
        $foodSharePoint = $this->getFoodSharePoint($foodSharePointId);

        if ($foodSharePoint->status === ActivationStatus::ACTIVE) {
            return; //FoodSharePoint has been created by orga member or the ambassador himself
        }

        $region = $this->regionGateway->getRegion($foodSharePoint->regionId);

        $fspWGId = $this->groupFunctionGateway->getRegionFunctionGroupId($region['id'], WorkgroupFunction::FSP);
        if (empty($fspWGId)) {
            $fspBellRecipients = $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $region['id']]);
        } else {
            $fspBellRecipients = $this->db->fetchAllValuesByCriteria('fs_botschafter', 'foodsaver_id', ['bezirk_id' => $fspWGId]);
        }

        $bellData = Bell::create(
            'sharepoint_activate_title',
            'sharepoint_activate',
            'fas fa-recycle',
            ['href' => '/fairteiler/' . $foodSharePointId],
            ['bezirk' => $region['name'], 'name' => $foodSharePoint->name],
            BellType::createIdentifier(BellType::NEW_FOOD_SHARE_POINT, $foodSharePointId),
            false
        );
        $this->bellGateway->addBellForUsers($fspBellRecipients, $bellData);
    }

    private function removeBellNotificationForNewFoodSharePoint(int $foodSharePointId): void
    {
        $identifier = BellType::createIdentifier(BellType::NEW_FOOD_SHARE_POINT, $foodSharePointId);
        if (!$this->bellGateway->bellWithIdentifierExists($identifier)) {
            return;
        }
        $this->bellGateway->delBellsByIdentifier($identifier);
    }

    public function getFollowerStatus(int $foodSharePointId, int $userId): int
    {
        try {
            return $this->db->fetchValueByCriteria('fs_fairteiler_follower', 'type', [
                'fairteiler_id' => $foodSharePointId,
                'foodsaver_id' => $userId,
            ]);
        } catch (Exception) {
            return 0;
        }
    }

    public function isManagerForFoodSharePoint(int $userId, int $foodSharePointId): bool
    {
        return $this->db->exists('fs_fairteiler_follower', [
            'fairteiler_id' => $foodSharePointId,
            'foodsaver_id' => $userId,
            'type' => FollowerType::FOOD_SHARE_POINT_MANAGER,
        ]);
    }
}
