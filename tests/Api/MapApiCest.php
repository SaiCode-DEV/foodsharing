<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Tests\Support\ApiTester;

class MapApiCest
{
    private $region;
    private $communityPin;
    private $foodSharePoint;
    private $user;
    private $basket;

    final public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
        $this->user = $I->createFoodsaver();
        $this->communityPin = $I->createCommunityPin($this->region['id']);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN_SEARCHING->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::CLOSED->value, 'betrieb_status_id' => CooperationStatus::GIVES_TO_OTHER_CHARITY->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN_SEARCHING->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::CLOSED->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->createStore($this->region['id'], null, null, ['lat' => null, 'lon' => null, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $this->foodSharePoint = $I->createFoodSharePoint($this->user['id']);
        $this->basket = $I->createFoodbasket($this->user['id']);
    }

    final public function canFetchMarkersWithoutLogin(ApiTester $I): void
    {
        $I->sendGet('api/map/markers', ['types' => 'baskets']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/map/markers', ['types' => 'foodsharepoints']);
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/map/markers', ['types' => 'communities']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    final public function canNotFetchStoreMarkersWithoutLogin(ApiTester $I): void
    {
        $I->sendGet('api/map/markers', ['types' => 'stores']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    final public function canFetchStoreMarkersNoSettings(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers', ['types' => 'stores']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$.betriebe');
        $I->assertCount(1, $stores);
        $I->assertCount(6, $stores[0]);
    }

    final public function canFetchStoreMarkersSearchingForMembers(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers', ['types' => 'stores', 'status' => ['needhelpinstant']]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$.betriebe');
        $I->assertCount(1, $stores);
        $I->assertCount(2, $stores[0]);
    }

    final public function canFetchStoreMarkersOpenForMembers(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers', ['types' => 'stores', 'status' => ['needhelp']]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$.betriebe');
        $I->assertCount(1, $stores);
        $I->assertCount(2, $stores[0]);
    }

    final public function canFetchStoreMarkersShowNoCooperation(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers', ['types' => 'stores', 'status' => ['nkoorp']]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$.betriebe');
        $I->assertCount(1, $stores);
        $I->assertCount(1, $stores[0]);
    }

    final public function canFetchRegionBubble(ApiTester $I): void
    {
        $I->updateInDatabase('fs_region_pin', ['status' => RegionPinStatus::ACTIVE], ['region_id' => $this->region['id']]);
        $I->sendGet('api/map/regions/' . $this->region['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'description' => $this->communityPin['desc']
        ]);
    }

    final public function canNotFetchDescriptionOfInvalidRegion(ApiTester $I): void
    {
        $I->sendGet('api/map/regions/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canNotFetchDescriptionOfInactiveMarker(ApiTester $I): void
    {
        $I->updateInDatabase('fs_region_pin', ['status' => RegionPinStatus::INACTIVE], ['region_id' => $this->region['id']]);
        $I->sendGet('api/map/regions/' . $this->region['id']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canFetchFoodSharePointWithoutLogin(ApiTester $I)
    {
        $I->sendGet('api/map/foodSharePoint/' . $this->foodSharePoint['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotFetchFoodSharePointWithoutLogin(ApiTester $I)
    {
        $I->sendGet('api/map/foodSharePoint/' . ($this->foodSharePoint['id'] + 1));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canFetchBasketBubble(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/baskets/' . $this->basket['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->basket['id'],
            'description' => $this->basket['description'],
            'photo' => $this->basket['picture'],
            'creator' => [
                'id' => $this->user['id']
            ],
        ]);
    }

    final public function canNotFetchBubbleOfInvalidBasket(ApiTester $I)
    {
        $I->sendGet('api/map/baskets/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canOnlySeeBasketDetailsWhenLoggedIn(ApiTester $I)
    {
        $I->sendGet('api/map/baskets/' . $this->basket['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->cantSeeResponseContainsJson([
            'creator' => [
                'id' => $this->user['id']
            ],
        ]);
    }
}
