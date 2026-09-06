<?php

namespace Foodsharing\Modules\Map;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Map\DTO\BasketBubbleData;
use Foodsharing\Modules\Map\DTO\MapMarker;

class MapGateway extends BaseGateway
{
    public function __construct(
        Database $db
    ) {
        parent::__construct($db);
    }

    /**
     * Returns the location of a store, or null if the ID does not exist.
     */
    public function getStoreLocation(int $storeId): ?GeoLocation
    {
        $location = $this->db->fetchByCriteria('fs_betrieb', ['lat', 'lon'], ['id' => $storeId]);

        return empty($location) ? null : new GeoLocation(floatval($location['lat']), floatval($location['lon']));
    }

    /**
     * Returns the location of a food share point, or null if the ID does not exist.
     */
    public function getFoodSharePointLocation(int $foodSharePointId): ?GeoLocation
    {
        $location = $this->db->fetchByCriteria('fs_fairteiler', ['lat', 'lon'], ['id' => $foodSharePointId]);

        return empty($location) ? null : new GeoLocation(floatval($location['lat']), floatval($location['lon']));
    }

    /**
     * @return MapMarker[]
     */
    public function getBasketMarkers(): array
    {
        $markers = $this->db->fetchAll('SELECT
                `id`, `lat`, `lon`, LEFT(`description`, 30) as `name`
            FROM fs_basket
            WHERE `status` = 1');

        return array_map(fn ($marker) => MapMarker::create($marker['id'], $marker['name'], $marker['lat'], $marker['lon']), $markers);
    }

    public function getFoodSharePointMarkers(): array
    {
        $markers = $this->db->fetchAllByCriteria('fs_fairteiler', ['id', 'lat', 'lon', 'name'], [
            'status' => 1,
            'lat !=' => ''
        ]);

        return array_map(fn ($marker) => MapMarker::create($marker['id'], $marker['name'], $marker['lat'], $marker['lon']), $markers);
    }

    public function getRegionMarkers(): array
    {
        $markers = $this->db->fetchAll("SELECT
                r.id, p.lat, p.lon, r.name
            FROM fs_region_pin p
            JOIN fs_bezirk r ON r.id = p.region_id
            WHERE p.lat != '' AND p.status = ?",
            [RegionPinStatus::ACTIVE]);

        return array_map(fn ($marker) => MapMarker::create($marker['id'], $marker['name'], $marker['lat'], $marker['lon']), $markers);
    }

    /**
     * Returns all public events that have valid coordinates. Online events do not have coordinates and are not shown
     * on the map.
     *
     * @return MapMarker[]
     */
    public function getEventMarkers(): array
    {
        $markers = $this->db->fetchAll('SELECT
                e.id, l.lat, l.lon, e.name
            FROM fs_event e
            INNER JOIN fs_location l ON l.id = e.location_id
            WHERE e.is_public = 1 AND e.end > NOW()
            AND l.lat IS NOT NULL AND l.lon IS NOT NULL');

        return array_map(fn ($marker) => MapMarker::create($marker['id'], $marker['name'], $marker['lat'], $marker['lon']), $markers);
    }

    /**
     * Returns the data for a basket's bubble on the map or null if the basket does not exist.
     *
     * @param bool $includeDetails whether to include details that should not be visible when logged out
     */
    public function getBasketBubbleData(int $basketId, bool $includeDetails): ?BasketBubbleData
    {
        $basket = $this->db->fetch('
			SELECT
				b.id,
				b.status,
				b.description,
				b.picture,
				b.foodsaver_id,
				UNIX_TIMESTAMP(b.time) AS created_at,
				fs.id AS fs_id,
				fs.name AS fs_name,
				fs.photo AS fs_photo,
				fs.is_sleeping AS fs_is_sleeping
			FROM
				fs_basket b
			INNER JOIN
				fs_foodsaver fs
			ON
				b.foodsaver_id = fs.id
			AND
				b.id = :id
		', [
            ':id' => $basketId,
        ]);
        if (empty($basket)) {
            return null;
        }

        $pictureData = json_decode($basket['picture'] ?? '', true);
        $picture = match (true) {
            is_array($pictureData) => $pictureData,
            is_null($pictureData) => [],
            default => [$pictureData],
        };

        $bubbleData = BasketBubbleData::create($basket['id'], $basket['description'], $picture);
        if ($includeDetails) {
            $bubbleData->createdAt = Carbon::createFromTimestamp($basket['created_at'], new DateTimeZone('Europe/Berlin'));
            $bubbleData->creator = new Profile($basket['fs_id'], $basket['fs_name'], $basket['fs_photo'], (bool)$basket['fs_is_sleeping']);
        }

        return $bubbleData;
    }
}
