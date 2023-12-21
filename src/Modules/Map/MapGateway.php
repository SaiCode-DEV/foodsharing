<?php

namespace Foodsharing\Modules\Map;

use Carbon\Carbon;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
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

    public function getStoreLocation(int $storeId): array
    {
        return $this->db->fetchByCriteria('fs_betrieb', ['lat', 'lon'], ['id' => $storeId]);
    }

    public function getFoodsaverLocation(int $foodsaverId): array
    {
        return $this->db->fetchByCriteria('fs_foodsaver', ['lat', 'lon'], ['id' => $foodsaverId]);
    }

    public function getBasketMarkers(): array
    {
        $markers = $this->db->fetchAllByCriteria('fs_basket', ['id', 'lat', 'lon'], [
            'status' => 1
        ]);

        return array_map(function ($x) {
            return MapMarker::create($x['id'], $x['lat'], $x['lon']);
        }, $markers);
    }

    public function getFoodSharePointMarkers(): array
    {
        $markers = $this->db->fetchAllByCriteria('fs_fairteiler', ['id', 'lat', 'lon', 'bezirk_id'], [
            'status' => 1,
            'lat !=' => ''
        ]);

        return array_map(function ($x) {
            return MapMarker::create($x['id'], $x['lat'], $x['lon'], $x['bezirk_id']);
        }, $markers);
    }

    public function getCommunityMarkers(): array
    {
        $markers = $this->db->fetchAllByCriteria('fs_region_pin', ['region_id', 'lat', 'lon'], [
            'lat !=' => '',
            'status' => RegionPinStatus::ACTIVE
        ]);

        return array_map(function ($x) {
            return MapMarker::create($x['region_id'], $x['lat'], $x['lon']);
        }, $markers);
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
				fs.sleep_status AS fs_sleep_status
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

        $bubbleData = BasketBubbleData::create($basket['id'], $basket['description'], $basket['picture']);
        if ($includeDetails) {
            $bubbleData->createdAt = Carbon::createFromTimestamp('created_at');
            $bubbleData->creator = new Profile($basket['fs_id'], $basket['fs_name'], $basket['fs_photo'], $basket['fs_sleep_status']);
        }

        return $bubbleData;
    }
}
