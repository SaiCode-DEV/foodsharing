<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Categories\StoreCategoryType;
use Foodsharing\Modules\Core\DBConstants\Region\RegionPinStatus;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\Store\TeamSearchStatus;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class MapApiCest
{
    private $region;
    private $communityPin;
    private $foodSharePoint;
    private $user;
    private $basket;
    private $stores;
    private $categories;

    final public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $this->user = $I->createFoodsaver();
        $this->communityPin = $I->createCommunityPin($this->region['id']);

        // Create store categories
        $this->categories = [];
        $I->clearTable('fs_betrieb_kategorie');
        $this->categories[] = $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 5, 'name' => 'Category Pickup', 'type' => StoreCategoryType::PICKUP->value]);
        $this->categories[] = $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 9, 'name' => 'Category Giving', 'type' => StoreCategoryType::GIVING->value]);
        $this->categories[] = $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 13, 'name' => 'Category Orga', 'type' => StoreCategoryType::ORGA->value]);

        // Create stores
        $this->stores = [];
        // Pickup store
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN_SEARCHING->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[0]]);
        // Giving store
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::CLOSED->value, 'betrieb_status_id' => CooperationStatus::GIVES_TO_OTHER_CHARITY->value, 'betrieb_kategorie_id' => $this->categories[1]]);
        // Orga store
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN_SEARCHING->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[2]]);
        // More pickup stores
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[0]]);
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[0]]);
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => 49.1, 'lon' => 5.2, 'team_status' => TeamSearchStatus::CLOSED->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[0]]);
        // Invalid store (no coordinates)
        $this->stores[] = $I->createStore($this->region['id'], null, null, ['lat' => null, 'lon' => null, 'team_status' => TeamSearchStatus::OPEN->value, 'betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value, 'betrieb_kategorie_id' => $this->categories[0]]);

        $this->foodSharePoint = $I->createFoodSharePoint($this->user['id']);
        $this->basket = $I->createFoodbasket($this->user['id']);
    }

    final public function canFetchMarkersWithoutLogin(ApiTester $I): void
    {
        $I->sendGet('api/map/markers/baskets');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/map/markers/food-share-points');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/map/markers/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    final public function canNotFetchStoreMarkersWithoutLogin(ApiTester $I): void
    {
        $I->sendGet('api/map/markers/stores');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    final public function canFetchStoreMarkersNoSettings(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(1, $stores);
        $I->assertCount(6, $stores[0]);
    }

    final public function canFetchStoreMarkersSearchingForMembers(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores', ['help' => 'searching']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(1, $stores);
        $I->assertCount(2, $stores[0]);
    }

    final public function canFetchStoreMarkersOpenForMembers(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores', ['help' => 'open']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(1, $stores);
        $I->assertCount(4, $stores[0]);
    }

    final public function canFetchStoreMarkersShowNoCooperation(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores', ['status' => 'not-cooperating']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(1, $stores);
        $I->assertCount(1, $stores[0]);
    }

    final public function canFetchRegionBubble(ApiTester $I): void
    {
        $I->updateInDatabase('fs_region_pin', ['status' => RegionPinStatus::ACTIVE], ['region_id' => $this->region['id']]);
        $I->sendGet('api/map/markers/regions/' . $this->region['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'description' => $this->communityPin['desc']
        ]);
    }

    final public function canNotFetchDescriptionOfInvalidRegion(ApiTester $I): void
    {
        $I->sendGet('api/map/markers/regions/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canNotFetchDescriptionOfInactiveMarker(ApiTester $I): void
    {
        $I->updateInDatabase('fs_region_pin', ['status' => RegionPinStatus::INACTIVE], ['region_id' => $this->region['id']]);
        $I->sendGet('api/map/markers/regions/' . $this->region['id']);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canFetchFoodSharePointWithoutLogin(ApiTester $I)
    {
        $I->sendGet('api/map/markers/food-share-points/' . $this->foodSharePoint['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function canNotFetchFoodSharePointWithoutLogin(ApiTester $I)
    {
        $I->sendGet('api/map/markers/food-share-points/' . ($this->foodSharePoint['id'] + 1));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canFetchBasketBubble(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/baskets/' . $this->basket['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->basket['id'],
            'description' => $this->basket['description'],
            'pictures' => [],
            'creator' => [
                'id' => $this->user['id']
            ],
        ]);
    }

    final public function canNotFetchBubbleOfInvalidBasket(ApiTester $I)
    {
        $I->sendGet('api/map/markers/baskets/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    final public function canOnlySeeBasketDetailsWhenLoggedIn(ApiTester $I)
    {
        $I->sendGet('api/map/markers/baskets/' . $this->basket['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->cantSeeResponseContainsJson([
            'creator' => [
                'id' => $this->user['id']
            ],
        ]);
    }

    final public function canFetchStoreMarkersPickup(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores', ['type' => StoreCategoryType::PICKUP->value]);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $stores = $I->grabDataFromResponseByJsonPath('$');
        $I->assertCount(1, $stores);
        $I->assertCount(4, $stores[0]);
    }

    final public function canFetchStoreBubble(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores/' . $this->stores[0]['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->stores[0]['id'],
            'name' => $this->stores[0]['name'],
            'regionId' => $this->stores[0]['bezirk_id'],
            'regionName' => $this->region['name'],
            'categoryType' => StoreCategoryType::PICKUP->value,
        ]);
    }

    final public function canFetchStoreBubbleGiving(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores/' . $this->stores[1]['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->stores[1]['id'],
            'name' => $this->stores[1]['name'],
            'regionId' => $this->stores[1]['bezirk_id'],
            'regionName' => $this->region['name'],
            'categoryType' => StoreCategoryType::GIVING->value,
        ]);
    }

    final public function canFetchStoreBubbleOrga(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores/' . $this->stores[2]['id']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'id' => $this->stores[2]['id'],
            'name' => $this->stores[2]['name'],
            'regionId' => $this->stores[2]['bezirk_id'],
            'regionName' => $this->region['name'],
            'categoryType' => StoreCategoryType::ORGA->value,
        ]);
    }

    final public function canNotFetchStoreBubbleWithoutLogin(ApiTester $I)
    {
        $I->sendGet('api/map/markers/stores/' . $this->stores[0]['id']);
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    final public function canNotFetchStoreOfNonexistingStore(ApiTester $I)
    {
        $I->login($this->user['email']);
        $I->sendGet('api/map/markers/stores/9999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
}
