<?php

namespace Foodsharing\Modules\Map;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Map\DTO\StoreMapBubbleData;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\TeamStatus;
use Foodsharing\Permissions\StorePermissions;
use Foodsharing\Utility\WeightHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MapTransactions
{
    public function __construct(
        private readonly StoreGateway $storeGateway,
        private readonly StorePermissions $storePermissions,
        private readonly Session $session,
        private readonly WeightHelper $weightHelper
    ) {
    }

    /**
     * Collects and returns all the data that is necessary for a store's bubble on the map.
     */
    public function getStoreMapData(int $storeId): StoreMapBubbleData
    {
        $store = $this->storeGateway->getMyStore($this->session->id(), $storeId);
        if (empty($store)) {
            throw new NotFoundHttpException('store does not exist');
        }

        $mapData = new StoreMapBubbleData();
        $mapData->id = $storeId;
        $mapData->name = $store['name'];
        $mapData->teamMemberCount = count($store['foodsaver']);
        $mapData->standbyCount = count($store['springer']);
        $mapData->location = GeoLocation::createFromArray($store);

        $pickupCount = intval($store['pickup_count']);
        if ($pickupCount > 0) {
            $mapData->pickupCount = $pickupCount;
            $mapData->pickupWeightInKg = $pickupCount * $this->weightHelper->mapIdToKilos($store['abholmenge']);
        }

        foreach ($store['foodsaver'] as $fs) {
            if ($fs['verantwortlich'] == 1) {
                $mapData->managers[] = new Profile([
                    'id' => $fs['id'],
                    'name' => $fs['firstName'],
                    'photo' => $fs['photo'],
                    'is_sleeping' => $fs['is_sleeping']
                ]);
            }
        }

        $mapData->cooperationStart = $store['begin'] ? Carbon::createFromFormat('Y-m-d', $store['begin']) : null;
        $mapData->publicInformation = $store['public_info'];
        $mapData->publicPickupTime = PublicTimes::tryFrom(intval($store['public_time'])) ?? PublicTimes::NOT_SET;
        $mapData->teamSearchStatus = TeamSearchStatus::tryFrom($store['team_status']) ?? TeamSearchStatus::CLOSED;
        $mapData->cooperationStatus = CooperationStatus::tryFrom($store['betrieb_status_id']) ?? CooperationStatus::UNCLEAR;

        // add permissions
        $teamStatus = $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId);
        $mapData->mayAccessStorePage = $teamStatus > TeamStatus::Applied || $this->storePermissions->mayEditStore($storeId);
        $mapData->maySendRequest = $mapData->teamSearchStatus != TeamSearchStatus::CLOSED && $teamStatus == TeamStatus::NoMember;
        $mapData->mayWithdrawRequest = $mapData->teamSearchStatus != TeamSearchStatus::CLOSED && $teamStatus == TeamStatus::Applied;

        return $mapData;
    }
}
