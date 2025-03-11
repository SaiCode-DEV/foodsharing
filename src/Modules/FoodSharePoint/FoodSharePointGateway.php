<?php

namespace Foodsharing\Modules\FoodSharePoint;

use Exception;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\FoodSharePoint\FollowerType;
use Foodsharing\Modules\Core\DBConstants\Info\InfoType;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Map\DTO\MapMarker;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointEditData;
use Foodsharing\RestApi\Models\FoodSharePoint\FoodSharePointForCreation;
use Foodsharing\RestApi\Models\Notifications\FoodSharePoint;

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

    public function listActiveFoodSharePoints(array $regionIds): array
    {
        if (!$regionIds) {
            return [];
        }
        if ($foodSharePoints = $this->db->fetchAll(
            '
			SELECT 	`id`,
					`name`,
					`picture`
			FROM 	`fs_fairteiler`
			WHERE 	`bezirk_id` IN( ' . implode(',', $regionIds) . ' )
			AND 	`status` = 1
			ORDER BY `name`
		'
        )
        ) {
            foreach ($foodSharePoints as $fspKey => $fspValue) {
                $foodSharePoints[$fspKey]['pic'] = false;
                if (!empty($fspValue['picture'])) {
                    $foodSharePoints[$fspKey]['pic'] = $this->getPicturePaths($fspValue['picture']);
                }
            }

            return $foodSharePoints;
        }

        return [];
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

        return array_map([MapMarker::class, 'createFromArray'], $foodSharePoints);
    }

    public function listFoodsaversFoodSharePoints(int $fsId): array
    {
        return $this->db->fetchAll('
			SELECT
				ft.id,
				ft.name,
				ff.infotype,
				ff.`type`

			FROM
				`fs_fairteiler_follower` ff
				LEFT JOIN `fs_fairteiler` ft
				ON ff.fairteiler_id = ft.id

			WHERE
				ff.foodsaver_id = :fsId
		', [':fsId' => $fsId]);
    }

    public function listFoodSharePointsNested(array $regionIds = []): array
    {
        if (!empty($regionIds) && ($foodSharePoint = $this->db->fetchAll(
            '
			SELECT 	ft.`id`,
					ft.`name`,
					ft.`picture`,
					bz.id AS bezirk_id,
					bz.name AS bezirk_name

			FROM 	`fs_fairteiler` ft,
					`fs_bezirk` bz

			WHERE 	ft.bezirk_id = bz.id
			AND 	ft.`bezirk_id` IN(' . implode(',', $regionIds) . ')
			AND 	ft.`status` = 1
			ORDER BY ft.`name`
		'
        ))
        ) {
            $out = [];

            foreach ($foodSharePoint as $fsp) {
                if (!isset($out[$fsp['bezirk_id']])) {
                    $out[$fsp['bezirk_id']] = [
                        'id' => $fsp['bezirk_id'],
                        'name' => $fsp['bezirk_name'],
                        'fairteiler' => [],
                    ];
                }
                $pic = false;
                if (!empty($fsp['picture'])) {
                    $pic = $this->getPicturePaths($fsp['picture']);
                }
                $out[$fsp['bezirk_id']]['fairteiler'][] = [
                    'id' => $fsp['id'],
                    'name' => $fsp['name'],
                    'picture' => $fsp['picture'],
                    'pic' => $pic,
                ];
            }

            return $out;
        }

        return [];
    }

    public function listNearbyFoodSharePoints(GeoLocation $location, int $distanceInKm = 30): array
    {
        /* ST_BUFFER expects the distance to be in the same unit as the points. The factor of 1.5 makes sure that the
         bounding box is not too small due to Earth's curvature. */
        $maxDistanceInDegrees = 1.5 * $distanceInKm / (pi() * 6371) * 180;

        return $this->db->fetchAll(
            '
			SELECT
				ft.`id`,
				ft.`bezirk_id`,
				ft.`name`,
				ft.`picture`,
				ft.`status`,
				ft.`desc`,
				ft.`anschrift`,
				ft.`plz`,
				ft.`ort`,
				ft.`lat`,
				ft.`lon`,
				ft.`add_date`,
				UNIX_TIMESTAMP(ft.`add_date`) AS time_ts,
				ft.`add_foodsaver`,
				ST_Distance_Sphere(Point(:lon, :lat), Point(ft.lon, ft.lat)) / 1000 AS distance
			FROM
				`fs_fairteiler` ft
			WHERE
				ft.`status` = 1
			AND
                -- Reduce load for distance calculation by using a bounding box
                -- Only for all points inside the bounding box is the calculation running
                ST_INTERSECTS(Point(ft.lon, ft.lat),
                    ST_Envelope(
                        ST_BUFFER(
                            Point(:lon, :lat),
                            :max_distance_in_degrees
                        )
                    )
                )
			HAVING
				distance <= :distance
			ORDER BY
				distance
			LIMIT 6
		',
            [
                ':lat' => $location->lat,
                ':lon' => $location->lon,
                ':distance' => $distanceInKm,
                ':max_distance_in_degrees' => $maxDistanceInDegrees,
            ]
        );
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

    public function unfollowFoodSharePoints(int $fsId, array $foodSharePointIds): int
    {
        return $this->db->delete(
            'fs_fairteiler_follower',
            [
                'foodsaver_id' => $fsId,
                'fairteiler_id' => $foodSharePointIds
            ]
        );
    }

    public function updateInfoType(int $fsId, int $foodSharePointId, int $infoType): int
    {
        return $this->db->update(
            'fs_fairteiler_follower',
            ['infotype' => $infoType],
            [
                'foodsaver_id' => $fsId,
                'fairteiler_id' => $foodSharePointId
            ]
        );
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

    /**
     * TODO: split up the data for FSPs and followers into two functions, two DTOs. Replace the REST endpoint with
     * two endpoints. After that, mark getFoodSharePoint and getFollower as deprecated.
     */
    public function getFoodSharePointWithManagers(int $foodSharePointId): ?FoodSharePointData
    {
        $data = $this->db->fetch('SELECT
                ft.id AS food_share_point_id,
                ft.bezirk_id AS region_id,
                b.name AS region_name,
                ft.`name` AS food_share_point_name,
                ft.`picture`,
                ft.`status`,
                ft.`desc`,
                ft.`anschrift`,
                ft.`plz`,
                ft.`ort`,
                ft.`lat`,
                ft.`lon`,
                UNIX_TIMESTAMP(ft.`add_date`) AS add_date,
                ft.`add_foodsaver`,
                fs.id AS creator_id,
                fs.name AS creator_name,
                fs.photo AS creator_photo,
                COUNT(ff.foodsaver_id) AS follower_count
        FROM    fs_fairteiler ft
        JOIN fs_foodsaver fs ON ft.add_foodsaver = fs.id
        LEFT OUTER JOIN fs_fairteiler_follower ff ON ff.fairteiler_id = ft.id AND ff.type = :followerType
        JOIN fs_bezirk b ON b.id = ft.bezirk_id
        WHERE ft.id = :foodSharePointId
        ', [
            ':foodSharePointId' => $foodSharePointId,
            ':followerType' => FollowerType::FOLLOWER,
        ]);
        if (empty($data)) {
            return null;
        }
        $foodSharePoint = FoodSharePointData::createFromArray($data);

        $managers = $this->db->fetchAll('SELECT
                fs.id, fs.name, fs.photo, fs.is_sleeping
        FROM fs_fairteiler_follower ff
        JOIN fs_foodsaver fs ON fs.id = ff.foodsaver_id
        WHERE ff.fairteiler_id = :foodSharePointId
        AND ff.type = :managerType
        ', [
            ':foodSharePointId' => $foodSharePointId,
            ':managerType' => FollowerType::FOOD_SHARE_POINT_MANAGER,
        ]);
        $foodSharePoint->setManagers($managers);

        return $foodSharePoint;
    }

    public function getFoodSharePoint(int $foodSharePointId): array
    {
        if ($foodSharePoint = $this->db->fetch(
            '
			SELECT 	ft.id,
					ft.`bezirk_id`,
					ft.`name`,
					ft.`picture`,
					ft.`status`,
					ft.`desc`,
					ft.`anschrift`,
					ft.`plz`,
					ft.`ort`,
					ft.`lat`,
					ft.`lon`,
					ft.`add_date`,
					UNIX_TIMESTAMP(ft.`add_date`) AS time_ts,
					ft.`add_foodsaver`,
					fs.name AS fs_name,
					fs.nachname AS fs_nachname,
					fs.id AS fs_id

			FROM 	fs_fairteiler ft
			LEFT JOIN
					fs_foodsaver fs


			ON 	ft.add_foodsaver = fs.id
			WHERE 	ft.id = :foodSharePointId
		',
            [':foodSharePointId' => $foodSharePointId]
        )
        ) {
            $foodSharePoint['pic'] = false;
            if (!empty($foodSharePoint['picture'])) {
                $foodSharePoint['pic'] = $this->getPicturePaths($foodSharePoint['picture']);
            }

            return $foodSharePoint;
        }

        return [];
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

        if ($foodSharePoint['status'] === 1) {
            return; //FoodSharePoint has been created by orga member or the ambassador himself
        }

        $region = $this->regionGateway->getRegion($foodSharePoint['bezirk_id']);

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
            ['href' => '/?page=fairteiler&sub=check&id=' . $foodSharePointId],
            ['bezirk' => $region['name'], 'name' => $foodSharePoint['name']],
            BellType::createIdentifier(BellType::NEW_FOOD_SHARE_POINT, $foodSharePointId),
            false
        );
        $this->bellGateway->addBell($fspBellRecipients, $bellData);
    }

    private function removeBellNotificationForNewFoodSharePoint(int $foodSharePointId): void
    {
        $identifier = BellType::createIdentifier(BellType::NEW_FOOD_SHARE_POINT, $foodSharePointId);
        if (!$this->bellGateway->bellWithIdentifierExists($identifier)) {
            return;
        }
        $this->bellGateway->delBellsByIdentifier($identifier);
    }

    /**
     * Returns the URL paths for the 'thumb', 'head', and 'orig' version of the picture. This differentiates between
     * newer files, which are requested via rest API, and older files, which are requested directly with their file
     * path.
     *
     * @param string $picture a picture file's name
     *
     * @return array URL paths for the 'thumb', 'head', 'orig' version
     */
    private function getPicturePaths(string $picture): array
    {
        if (str_starts_with($picture, '/api/uploads/')) {
            return [
                'thumb' => $picture . '?h=60&w=60',
                'head' => $picture . '?h=169&w=525',
                'orig' => $picture
            ];
        }

        return [
            'thumb' => 'images/' . str_replace('/', '/crop_1_60_', $picture),
            'head' => 'images/' . str_replace('/', '/crop_0_528_', $picture),
            'orig' => 'images/' . $picture,
        ];
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
