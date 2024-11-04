<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Modules\Basket\DTO\Basket;
use Foodsharing\Modules\Basket\DTO\BasketForListView;
use Foodsharing\Modules\Basket\DTO\BasketForOwnerMenu;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Basket\Status as BasketStatus;
use Foodsharing\Modules\Core\DBConstants\BasketRequests\Status as RequestStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;

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
                'tel' => strip_tags($basket->telephone ?? null),
                'handy' => strip_tags($basket->mobile ?? null),
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
            UNIX_TIMESTAMP(b.time) AS time_ts,
            UNIX_TIMESTAMP(b.update) AS update_ts,
            UNIX_TIMESTAMP(b.until) AS until_ts,
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
            INNER JOIN fs_foodsaver fs ON b.foodsaver_id = fs.id
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
                'tel' => strip_tags($basket->telephone ?? null),
                'handy' => strip_tags($basket->mobile ?? null),
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
				UNIX_TIMESTAMP(`time`) AS time_ts
			FROM fs_basket
			WHERE `foodsaver_id` = :foodsaver_id
			AND `status` = :status
			AND `until` > NOW()', [
            ':foodsaver_id' => $foodsaverId,
            ':status' => BasketStatus::REQUESTED_MESSAGE_READ
        ]);

        return array_map([BasketForOwnerMenu::class, 'createFromArray'], $baskets);
    }

    /**
     * Lists all requests for baskets of a given user.
     */
    public function getBasketRequestData(int $foodsaverId): array
    {
        return $this->db->fetchAll('SELECT
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
        $spatialPoint = sprintf('POINT(%f %f)', $gpsCoordinate->lon, $gpsCoordinate->lat);

        $baskets = $this->db->fetchAll('SELECT
				b.id,
			    UNIX_TIMESTAMP(b.`until`) AS until_ts,
				b.picture,
				b.description,
                ST_Distance_Sphere(ST_GeomFromText(:centerPoint), Point(b.lon, b.lat)) / 1000 AS distance,
				fs.id AS fs_id,
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				fs.is_sleeping AS fs_is_sleeping
			FROM fs_basket b
            JOIN fs_foodsaver fs ON b.foodsaver_id = fs.id
            WHERE
                -- Reduce load for distance calculation by using a bounding box
                -- Only for all points inside the bounding box is the calculation running
                ST_INTERSECTS(Point(b.lon, b.lat),
                    ST_Envelope(
                        ST_BUFFER(
                            ST_GeomFromText(:centerPoint),
                            :distanceKm * 1000
                        )
                    )
                )
			AND b.status = :status
			AND foodsaver_id != :fs_id
			AND b.until > NOW()
			HAVING distance <= :distanceKm
			ORDER BY distance
			LIMIT 10
		',
            [
                ':centerPoint' => $spatialPoint,
                ':status' => BasketStatus::REQUESTED_MESSAGE_READ,
                ':fs_id' => $userId ?? 0,
                ':distanceKm' => $distanceKm,
            ]
        );

        return array_map([BasketForListView::class, 'createFromArray'], $baskets);
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
