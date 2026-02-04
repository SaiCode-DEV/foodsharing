<?php

namespace Foodsharing\Modules\Basket;

use Carbon\Carbon;
use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForListView;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Basket\DTO\BasketRequest;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Basket\Status as BasketStatus;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;

class BasketGateway extends BaseGateway
{
    public function addBasket(
        Basket $basket,
        $region_id,
        $userId
    ): int {
        $appost = 1;

        if (isset($_REQUEST['appost']) && '0' === $_REQUEST['appost']) {
            $appost = 0;
        }

        return $this->db->insert(
            'fs_basket',
            [
                'foodsaver_id' => $userId,
                'status' => BasketStatus::REQUESTED_MESSAGE_READ,
                'time' => date('Y-m-d H:i:s'),
                'description' => $basket->description,
                'picture' => json_encode($basket->pictures),
                'tel' => strip_tags((string)($basket->telephone ?? null)),
                'handy' => strip_tags((string)($basket->mobile ?? null)),
                'contact_type' => implode(':', $basket->contactTypes),
                'location_type' => 0,
                'weight' => (float)$basket->weightInGrams / 1000,
                'lat' => $basket->lat,
                'lon' => $basket->lon,
                'bezirk_id' => (int)$region_id,
                'appost' => $appost,
                'until' => date('Y-m-d H:i:s', time() + $basket->lifeTimeInDays * 24 * 60 * 60),
            ]
        );
    }

    /**
     * Fetches a basket from the database. Returns details of the basket with
     * the given id or false if the basket does not yet exist. If the status is
     * set only a basket that matches this will be returned.
     *
     * @param int $id the basket's id
     * @param int|bool $status a basket status or false
     *
     * @return ?Basket the details of the basket or null if that basket doesn't exist
     */
    public function getBasket($id, $status = false): ?Basket
    {
        $status_sql = $status ? ('AND `status` = ' . (int)$status) : '';

        $basket = $this->db->fetch('SELECT
            b.id,
            b.status,
            b.description,
            b.picture,
            b.contact_type,
            b.tel,
            b.handy,
            b.lat,
            b.lon,
            b.weight AS weightInKg,
            b.time,
            b.update,
            b.until,
            fs.id AS fs_id,
            fs.name AS fs_name,
            fs.photo AS fs_photo,
            fs.is_sleeping AS fs_is_sleeping,
            COUNT(a.foodsaver_id) AS request_count
        FROM fs_basket b
        INNER JOIN fs_foodsaver fs ON b.foodsaver_id = fs.id
        LEFT OUTER JOIN fs_basket_anfrage a ON a.basket_id = b.id AND a.`status` IN(:status_unread,:status_read)
        WHERE b.id = :id
        ' . $status_sql, [
            ':id' => $id,
            ':status_unread' => RequestStatus::REQUESTED_MESSAGE_UNREAD,
            ':status_read' => RequestStatus::REQUESTED_MESSAGE_READ
        ]);

        return empty($basket['id']) ? null : Basket::createFromArray($basket);
    }

    /**
     * Lists all requests for a given basket.
     */
    public function listRequests(int $basket_id): array
    {
        return $this->db->fetchAll('SELECT
                UNIX_TIMESTAMP(a.time) AS time_ts,
                fs.name AS fs_name,
                fs.photo AS fs_photo,
                fs.id AS fs_id,
                fs.geschlecht AS fs_gender,
                fs.is_sleeping,
                b.id
            FROM fs_basket_anfrage a
            INNER JOIN fs_basket b ON a.basket_id = b.id
            INNER JOIN fs_foodsaver fs ON a.foodsaver_id = fs.id
            WHERE
                a.`status` IN(:status_unread,:status_read)
                AND b.id = :basket_id',
            [
                ':status_unread' => RequestStatus::REQUESTED_MESSAGE_UNREAD,
                ':status_read' => RequestStatus::REQUESTED_MESSAGE_READ,
                ':basket_id' => $basket_id,
            ]
        );
    }

    public function getRequest(int $basket_id, ?int $foodsaver_id_requester, $foodsaver_id_offerer): array
    {
        $stm = '
				SELECT
					UNIX_TIMESTAMP(a.time) AS time_ts,
					fs.name AS fs_name,
					fs.photo AS fs_photo,
					fs.id AS fs_id,
					fs.geschlecht AS fs_gender,
					b.id
				FROM
					fs_basket_anfrage a,
					fs_basket b,
					fs_foodsaver fs
				WHERE
					a.basket_id = b.id
				AND
					a.`status` IN(:status_unread,:status_read)
				AND
					a.foodsaver_id = fs.id
				AND
					b.foodsaver_id = :foodsaver_id_offerer
				AND
					a.foodsaver_id = :foodsaver_id_requester
				AND
					a.basket_id = :basket_id
				';

        return $this->db->fetch(
            $stm,
            [
                ':status_unread' => RequestStatus::REQUESTED_MESSAGE_UNREAD,
                ':status_read' => RequestStatus::REQUESTED_MESSAGE_READ,
                ':foodsaver_id_offerer' => $foodsaver_id_offerer,
                ':foodsaver_id_requester' => $foodsaver_id_requester,
                ':basket_id' => $basket_id,
            ]
        );
    }

    public function getRequestStatus(int $basket_id, int $foodsaver_id_requester, int $foodsaver_id_offerer): array
    {
        $stm = '
				SELECT
					a.`status`
				FROM
					fs_basket_anfrage a,
					fs_basket b
				WHERE
					a.basket_id = b.id
				AND
					b.foodsaver_id = :foodsaver_id_offerer
				AND
					a.foodsaver_id = :foodsaver_id_requester
				AND
					a.basket_id = :basket_id
				';

        return $this->db->fetch(
            $stm,
            [
                ':foodsaver_id_offerer' => $foodsaver_id_offerer,
                ':foodsaver_id_requester' => $foodsaver_id_requester,
                ':basket_id' => $basket_id,
            ]
        );
    }

    public function getUpdateCount(int $foodsaverId): int
    {
        $stm = '
				SELECT COUNT(a.basket_id)
				FROM fs_basket_anfrage a, fs_basket b
				WHERE a.basket_id = b.id
				AND a.`status` = :status
				AND b.foodsaver_id = :foodsaver_id
			';

        return (int)$this->db->fetchValue(
            $stm,
            [':status' => RequestStatus::REQUESTED_MESSAGE_UNREAD, ':foodsaver_id' => $foodsaverId]
        );
    }

    public function addTypes(int $basket_id, array $types): void
    {
        if (!empty($types)) {
            foreach ($types as $type) {
                $this->db->insert('fs_basket_has_types', ['basket_id' => $basket_id, 'types_id' => $type]);
            }
        }
    }

    public function addKind(int $basket_id, array $kinds): void
    {
        if (!empty($kinds)) {
            foreach ($kinds as $kind) {
                $this->db->insert('fs_basket_has_art', ['basket_id' => $basket_id, 'art_id' => $kind]);
            }
        }
    }

    public function removeBasket(int $basketId): int
    {
        return $this->db->update(
            'fs_basket',
            [
                'picture' => null,
                'status' => BasketStatus::DELETED_OTHER_REASON,
                'update' => date('Y-m-d H:i:s')
            ],
            ['id' => $basketId]
        );
    }

    public function editBasket(
        int $id,
        Basket $basket,
        ?int $fsId
    ): int {
        return $this->db->update(
            'fs_basket',
            [
                'update' => date('Y-m-d H:i:s'),
                'description' => $basket->description,
                'picture' => json_encode($basket->pictures),
                'lat' => $basket->lat,
                'lon' => $basket->lon,
                'tel' => strip_tags((string)($basket->telephone ?? null)),
                'handy' => strip_tags((string)($basket->mobile ?? null)),
                'contact_type' => implode(':', $basket->contactTypes),
                'weight' => (float)$basket->weightInGrams / 1000,
            ],
            ['id' => $id, 'foodsaver_id' => $fsId]
        );
    }

    /**
     * @return array<BasketForOwnerMenu>
     */
    public function listMyBaskets(int $foodsaverId): array
    {
        $baskets = $this->db->fetchAll('SELECT
				`id`,
				`description`,
				`picture`,
				`time`
			FROM fs_basket
			WHERE `foodsaver_id` = :foodsaver_id
			AND `status` = :status
			AND `until` > NOW()
            ORDER BY `time` ASC', [
            ':foodsaver_id' => $foodsaverId,
            ':status' => BasketStatus::REQUESTED_MESSAGE_READ
        ]);

        $picture = json_decode($baskets['picture'] ?? '', true);

        return array_map(fn ($data) => BasketForOwnerMenu::create(
            $data['id'],
            $data['description'],
            is_array($picture) ? ($picture[0] ?? null) : $data['picture'],
            new Carbon($data['time']),
        ), $baskets);
    }

    /**
     * Lists all requests for baskets of a given user.
     * @return BasketRequest[]
     */
    public function getBasketRequests(int $foodsaverId): array
    {
        $requests = $this->db->fetchAll('SELECT
				UNIX_TIMESTAMP(a.time) AS time_ts,
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				fs.id AS fs_id,
				fs.is_sleeping AS fs_is_sleeping,
				b.id,
				b.description
			FROM fs_basket_anfrage a
            JOIN fs_basket b ON b.id = a.basket_id
            JOIN fs_foodsaver fs ON fs.id = a.foodsaver_id
            WHERE a.`status` IN(:status_unread,:status_read)
			AND b.foodsaver_id = :foodsaver_id
			ORDER BY a.`time` DESC', [
            ':status_unread' => RequestStatus::REQUESTED_MESSAGE_UNREAD,
            ':status_read' => RequestStatus::REQUESTED_MESSAGE_READ,
            ':foodsaver_id' => $foodsaverId,
        ]);

        return array_map(function ($request) {
            return BasketRequest::create($request['id'], new Profile($request, 'fs_'), $request['time_ts']);
        }, $requests);
    }

    public function setStatus(int $basket_id, int $status, int $foodsaverId): void
    {
        $appost = 1;
        if (isset($_REQUEST['appost']) && '0' === $_REQUEST['appost']) {
            $appost = 0;
        }

        $this->db->insertOrUpdate('fs_basket_anfrage', [
            'foodsaver_id' => $foodsaverId,
            'basket_id' => $basket_id,
            'status' => $status,
            'time' => $this->db->now(),
            'appost' => $appost
        ]);
    }

    public function getAmountOfFoodBaskets(int $fs_id): int
    {
        return $this->db->count('fs_basket', ['foodsaver_id' => $fs_id]);
    }

    /**
     * Returns a list of baskets which are in side an search area.
     * The list provides the distance to the basket in kilometer.
     *
     * @param int|null $userId Filter baskets of user (null means no filter)
     * @param GeoLocation $gpsCoordinate Center of search area
     *
     * @return array<BasketForListView> List of all baskets inside search area without baskets of userid
     */
    public function listNearbyBasketsByDistance(?int $userId, GeoLocation $gpsCoordinate, int $distanceKm = 30): array
    {
        // Compute cheap approximate numeric bounding box deltas in degrees
        // $radiusEarth = 6371.0; // km
        // approx km per degree latitude
        // $kmPerDegLat = $radiusEarth * (pi() / 180); // ≈ 111.19492664455873 km/deg
        // $radiusEquator = 6378.137; // km
        // $kmPerDegLon = $radiusEquator * (pi() / 180.0); // ≈ 111.31949079327357 km/deg

        // Get delta degrees for latitude and longitude
        $deltaLat = $distanceKm / 111.19492664455873; // deg
        // We need to use cos() to adjust for the latitude (deviation from
        // equator) as we move north or south of the equator
        $deltaLon = $distanceKm / (111.31949079327357 * cos(deg2rad($gpsCoordinate->lat))); // deg

        /**
         * Retrieve nearby baskets (up to 10) matching a requested status, ordered by distance.
         *
         * Executes a single prepared SQL statement that:
         *  - selects baskets (fs_basket) joined with the foodsaver
         *    (fs_foodsaver),
         *  - filters by basket status, excluding baskets created by the current
         *    user, only returns baskets which are still open (b.until > NOW()),
         *  - pre-filters using a rectangular bounding-box filter on
         *    latitude/longitude to reduce rows,
         *  - computes the great-circle distance using
         *    ST_Distance_Sphere(Point(:lon, :lat), Point(b.lon, b.lat)) and
         *    converts it to kilometers,
         *  - filters results by maximum distance,
         *  - orders by ascending distance and limits the result set to 10 rows.
         *
         * Performance notes:
         *  - The bounding-box pre-filter (lat/lon BETWEEN ...) is used for performance to avoid
         *    running ST_Distance_Sphere on every row in the table
         */
        $baskets = $this->db->fetchAll(
            'SELECT
                b.id,
                b.`until`,
                b.picture,
                b.description,
                ST_Distance_Sphere(Point(:lon, :lat), Point(b.lon, b.lat)) / 1000 AS distance_in_km,
                fs.id AS fs_id,
                fs.name AS fs_name,
                fs.photo AS fs_photo,
                fs.is_sleeping AS fs_is_sleeping
            FROM fs_basket b
            JOIN fs_foodsaver fs ON b.foodsaver_id = fs.id
            WHERE b.status = :status
              AND b.foodsaver_id != :fs_id
              AND b.until > NOW()
              AND b.lat BETWEEN (:lat - :delta_lat) AND (:lat + :delta_lat)
              AND b.lon BETWEEN (:lon - :delta_lon) AND (:lon + :delta_lon)
            HAVING distance_in_km <= :max_distance_in_km
            ORDER BY distance_in_km
            LIMIT 10
        ', [
            ':lon' => $gpsCoordinate->lon,
            ':lat' => $gpsCoordinate->lat,
            ':status' => BasketStatus::REQUESTED_MESSAGE_READ,
            ':fs_id' => $userId ?? 0,
            ':max_distance_in_km' => $distanceKm,
            ':delta_lat' => $deltaLat,
            ':delta_lon' => $deltaLon,
        ]);

        return array_map(BasketForListView::createFromArray(...), $baskets);
    }

    public function listNewestBaskets(): array
    {
        return $this->db->fetchAll('
			SELECT
				b.id,
				b.`time`,
				UNIX_TIMESTAMP(b.`time`) AS time_ts,
				b.description,
				b.picture,
				b.contact_type,
				b.tel,
				b.handy,
			    b.until,
				fs.id AS fs_id,
				fs.name AS fs_name,
				fs.photo AS fs_photo
			FROM
				fs_basket b,
				fs_foodsaver fs
			WHERE
				b.foodsaver_id = fs.id
			AND
				b.status = :status
			AND
			    b.until > NOW()
			ORDER BY
				id DESC
			LIMIT
				0, 10
		', [':status' => BasketStatus::REQUESTED_MESSAGE_READ]);
    }

    /**
     * Sets the status of all active baskets of a user to 'deleted'.
     */
    public function removeActiveUserBaskets(int $userId): void
    {
        $this->db->update('fs_basket', [
            'status' => BasketStatus::DELETED_OTHER_REASON
        ], [
            'foodsaver_id' => $userId,
            'status' => BasketStatus::REQUESTED_MESSAGE_READ
        ]);
    }
}
