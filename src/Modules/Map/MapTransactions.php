<?php

namespace Foodsharing\Modules\Map;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Achievement\AchievementIDs;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\PublicTimes;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Map\DTO\StoreMapBubbleData;
use Foodsharing\Modules\Region\RegionGateway;
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
        private readonly WeightHelper $weightHelper,
        private readonly AchievementGateway $achievementGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly RegionGateway $regionGateway
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
        $mapData->regionId = $store['bezirk_id'];
        $mapData->regionName = $this->regionGateway->getRegionName($store['bezirk_id']);
        $mapData->teamMemberCount = count($store['foodsaver']);
        $mapData->standbyCount = count($store['springer']);
        $mapData->location = new GeoLocation(floatval($store['lat']), floatval($store['lon']));
        $mapData->isHygieneRequired = boolval($store['hygiene_requirement']);
        $mapData->hasHygieneCertificate = $this->achievementGateway->hasAchievement($this->session->id(), AchievementIDs::HYGIENE_CERTIFICATE);

        $mapData->hasCompleteProfile = $this->foodsaverGateway->isProfileComplete($this->session->id());
        $mapData->hasHomeRegion = $this->foodsaverGateway->hasHomeRegion($this->session->id());
        $mapData->isMemberOfRegion = $this->regionGateway->hasMember($this->session->id(), $store['bezirk_id']);
        $mapData->requireVerification = boolval($store['verified_requirement']) && !$this->session->isVerified();
        $mapData->requirePhone = boolval($store['phone_requirement']) && !$this->foodsaverGateway->hasPhone($this->session->id());
        $mapData->requireApplyText = boolval($store['apply_text_requirement']);

        $pickupCount = intval($store['pickup_count']);
        if ($pickupCount > 0) {
            $mapData->pickupCount = $pickupCount;
            $mapData->pickupWeightInKg = $pickupCount * $this->weightHelper->mapIdToKilos($store['abholmenge']);
        }

        foreach ($store['foodsaver'] as $fs) {
            if ($fs['verantwortlich'] == 1) {
                $mapData->managers[] = new Profile($fs['id'], $fs['firstName'], $fs['photo'], (bool)$fs['is_sleeping']);
            }
        }

        $mapData->cooperationStart = $store['begin'] ? Carbon::createFromFormat('!Y-m-d', $store['begin'], new DateTimeZone('UTC')) : null;
        $mapData->statusDate = $store['status_date'] ? Carbon::createFromFormat('!Y-m-d', $store['status_date'], new DateTimeZone('UTC')) : null;
        $mapData->publicInformation = $store['public_info'];
        $mapData->publicPickupTime = PublicTimes::tryFrom(intval($store['public_time'])) ?? PublicTimes::NOT_SET;
        $mapData->teamSearchStatus = TeamSearchStatus::tryFrom($store['team_status']) ?? TeamSearchStatus::CLOSED;
        $mapData->cooperationStatus = CooperationStatus::tryFrom($store['betrieb_status_id']) ?? CooperationStatus::UNCLEAR;

        // add permissions
        $teamStatus = $this->storeGateway->getUserTeamStatus($this->session->id(), $storeId);
        $mapData->mayAccessStorePage = $this->storePermissions->mayAccessStore($store['id']);
        $mapData->maySendRequest = $this->storePermissions->mayJoinStore($storeId, false);
        $mapData->mayAcceptInvitation = $this->storePermissions->mayJoinStore($storeId, true);
        $mapData->mayWithdrawRequest = $teamStatus === TeamStatus::Applied;

        $mapData->categoryType = StoreCategoryType::tryFrom($store['categoryType']) ?? StoreCategoryType::PICKUP;
        $mapData->isInvited = $teamStatus === TeamStatus::Invited;

        return $mapData;
    }
}
