<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

/**
 * Tests for the store api.
 */
/**
 * @group api-group-3
 */
class StoreApiCest
{
    private $store;
    private $foodsharer;
    private $user;
    private $unverifiedUser;
    private $teamMember;
    private $manager;
    private $region;
    private $otherRegion;
    private $nextRegion;

    private const string API_MAP_STORES = 'api/map/markers/stores';
    private const string API_STORES = 'api/stores';
    private const string API_REGIONS = 'api/regions';
    private const string EMAIL = 'email';
    private const string ID = 'id';

    private function createDefaultNewStoreJson(): array
    {
        return ['store' => [
            'name' => 'Store Name', 'regionId' => $this->region['id'],
            'location' => ['lat' => 50.01, 'lon' => 10.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ], 'firstPost' => null
        ];
    }

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
        $this->nextRegion = $I->createRegion();
        $this->otherRegion = $I->createRegion();
        $I->haveInDatabase('fs_chain', ['id' => 40, 'name' => 'Chain']);
        $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 20, 'name' => 'Category', 'type' => 0]);
        $this->foodsharer = $I->createFoodsharer(null, ['verified' => 0]);
        $this->user = $I->createFoodsaver(null, ['verified' => 0]);
        $this->unverifiedUser = $I->createFoodsaver(null, ['verified' => 0]);
        $this->teamMember = $I->createFoodsaver();
        $this->manager = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $this->store = $I->createStore($this->region['id'], null, null, ['kette_id' => 40, 'betrieb_kategorie_id' => 20, 'use_region_pickup_rule' => 1]);

        $I->addStoreTeam($this->store[self::ID], $this->teamMember[self::ID], false);

        $I->addRegionMember($this->nextRegion['id'], $this->manager['id']);
        $I->addStoreTeam($this->store[self::ID], $this->manager[self::ID], true);
    }

    public function canNotGetAccessToGetStoreAsUnknownUser(ApiTester $I)
    {
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/details');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    private function createGetStoreAsFoodsaverJsonTypes()
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'region' => [
                'id' => 'integer'
            ],
            'location' => [
                'lon' => 'float',
                'lat' => 'float'
            ],
            'category' => [
                'id' => 'integer'
            ],
            'cooperationStatus' => 'integer',
            'teamStatus' => 'integer',
            'chain' => [
                'id' => 'integer'
            ],
            'publicInfo' => 'string',
            'publicTime' => 'integer',
            'cooperationStart' => 'string',
            'calendarInterval' => 'integer',
            'weight' => 'integer',
            'createdAt' => 'string',
            'address' => [
                'street' => 'string',
                'city' => 'string',
                'postalCode' => 'string'
            ]
        ];
    }

    private function createGetStoreAsTeamMemberJsonType($store = [], $notExpected = false)
    {
        $store['publicity'] = $notExpected ? 'null' : 'integer';
        $store['description'] = $notExpected ? 'null' : 'string|null';
        $store['options'] = 'null';
        if (!$notExpected) {
            $store['options'] = [
                'useRegionPickupRule' => 'boolean'
            ];
        }

        return $store;
    }

    private function createGetStoreAsTeamMemberJson($store = [])
    {
        $store['description'] = $this->store['besonderheiten'];
        $store['publicity'] = $this->store['presse'];
        $store['options'] = [
            'useRegionPickupRule' => $this->store['use_region_pickup_rule'] == 1
        ];

        return $store;
    }

    private function createGetStoreAsStoreManagerJsonType($store = [], $notExpected = false)
    {
        $store['effort'] = $notExpected ? 'null' : 'integer|null';
        $store['updatedAt'] = $notExpected ? 'null' : 'string|null';
        $store['showsSticker'] = $notExpected ? 'null' : 'integer';
        $store['groceries'] = $notExpected ? 'null' : 'array|null';
        $store['contact'] = 'null';

        if (!$notExpected) {
            $store['contact'] = [
                'name' => 'string',
                'phone' => 'string',
                'fax' => 'string',
                'email' => 'string'
            ];
        }

        return $store;
    }

    private function createGetStoreAsStoreManagerJson($store = [])
    {
        $store['effort'] = $this->store['ueberzeugungsarbeit'];
        $store['updatedAt'] = $this->store['status_date'];
        $store['showsSticker'] = $this->store['sticker'];
        $store['groceries'] = [];
        $store['contact'] = [
            'name' => $this->store['ansprechpartner'],
            'phone' => $this->store['telefon'],
            'fax' => $this->store['fax'],
            'email' => $this->store['email']
        ];

        return $store;
    }

    public function getNotFoundToGetStoreInformation(ApiTester $I)
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] + 1 . '/details');
        $I->seeResponseCodeIs(Http::NOT_FOUND);
    }

    public function getAccessToGetStoreInformationAsUnVerifiedFoodsaver(ApiTester $I)
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/details');
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function getAccessToGetStoreInformationAsStoreManager(ApiTester $I)
    {
        $I->login($this->manager[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->createGetStoreAsStoreManagerJson());

        $storeType = $this->createGetStoreAsFoodsaverJsonTypes();
        $storeType = $this->createGetStoreAsTeamMemberJsonType($storeType, false);
        $storeType = $this->createGetStoreAsStoreManagerJsonType($storeType, false);
        $I->seeResponseMatchesJsonType($storeType);
    }

    public function getAccessToGetStoreInformationAsOrga(ApiTester $I)
    {
        $orga = $I->createOrga();

        $I->login($orga[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $json = $this->createGetStoreAsStoreManagerJson();
        $I->seeResponseContainsJson($json);

        $storeType = $this->createGetStoreAsFoodsaverJsonTypes();
        $storeType = $this->createGetStoreAsTeamMemberJsonType($storeType, false);
        $storeType = $this->createGetStoreAsStoreManagerJsonType($storeType, false);
        $I->seeResponseMatchesJsonType($storeType);
    }

    public function getAccessToGetStoreInformationAsTeamMember(ApiTester $I)
    {
        $I->login($this->teamMember[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/details');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->createGetStoreAsTeamMemberJson());
        $I->dontSeeResponseContainsJson($this->createGetStoreAsStoreManagerJson());

        $storeType = $this->createGetStoreAsFoodsaverJsonTypes();
        $storeType = $this->createGetStoreAsTeamMemberJsonType($storeType, false);
        $storeType = $this->createGetStoreAsStoreManagerJsonType($storeType, true);
        $I->seeResponseMatchesJsonType($storeType);
    }

    public function canNotGetAccessToCommonStoreMetadataAsUnknownUser(ApiTester $I): void
    {
        $I->sendGET(self::API_STORES . '/meta-data');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function getCommonStoreMetadataAsFoodsaver(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/meta-data');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maxCountPickupSlot' => 50]);
        $storeChains = $I->grabDataFromResponseByJsonPath('$.storeChains');
        $I->assertNotCount(0, $storeChains);
        $groceries = $I->grabDataFromResponseByJsonPath('$.groceries');
        $I->assertNotCount(0, $groceries);
        $categories = $I->grabDataFromResponseByJsonPath('$.categories');
        $I->assertNotCount(0, $categories);
        $status = $I->grabDataFromResponseByJsonPath('$.status');
        $I->assertNotCount(0, $status);
        $weight = $I->grabDataFromResponseByJsonPath('$.weight');
        $I->assertNotCount(0, $weight);
        $convinceStatus = $I->grabDataFromResponseByJsonPath('$.convinceStatus');
        $I->assertNotCount(0, $convinceStatus);
        $publicTimes = $I->grabDataFromResponseByJsonPath('$.publicTimes');
        $I->assertNotCount(0, $publicTimes);
    }

    public function getCommonStoreMetadataAsStoreOwner(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/meta-data');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maxCountPickupSlot' => 50]);
        $storeChains = $I->grabDataFromResponseByJsonPath('$.storeChains');
        $I->assertNotCount(0, $storeChains);
    }

    public function canAnonymUserNotAccessToGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionRelatedRegion = $I->createRegion();

        $I->sendGET(self::API_REGIONS . '/' . $regionRelatedRegion['id'] . '/stores');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function canNotAccessToGetListOfStoresWithInvalidRegion(ApiTester $I): void
    {
        $I->sendGET(self::API_REGIONS . '/1234/stores');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function foodsharerCanNotAccessToGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionRelatedRegion = $I->createRegion();

        $I->login($this->foodsharer[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionRelatedRegion['id'] . '/stores');
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function unverifiedFoodsaverCanAccessToGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionRelatedRegion = $I->createRegion();

        $I->login($this->unverifiedUser[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionRelatedRegion['id'] . '/stores');
        $I->seeResponseCodeIs(Http::OK);
    }

    public function verifiedFoodsaverCanAccessToGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionRelatedRegion = $I->createRegion();

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionRelatedRegion['id'] . '/stores');
        $I->seeResponseCodeIs(Http::OK);
    }

    public function foodsaverWithRegionRelationCanAccessToGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionRelatedRegion = $I->createRegion();
        $I->addRegionMember($regionRelatedRegion['id'], $this->user['id'], true);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionRelatedRegion['id'] . '/stores');
        $I->seeResponseCodeIs(Http::OK);
    }

    public function testContentofGetListOfStoresInRegion(ApiTester $I): void
    {
        $regionTop = $I->createRegion(null, ['type' => UnitType::CITY], false);
        $I->addRegionMember($regionTop['id'], $this->user['id'], true);

        $regionChild1 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN], false);
        $store1 = $I->createStore($regionChild1['id']);
        $regionChild2 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN], false);
        $store2 = $I->createStore($regionChild2['id']);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionTop['id'] . '/stores');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $ids = $I->grabDataFromResponseByJsonPath('$.*.id');
        $I->assertContains($store1[self::ID], $ids);
        $I->assertContains($store2[self::ID], $ids);

        $names = $I->grabDataFromResponseByJsonPath('$.*.name');
        foreach ($names as $name) {
            $I->assertNotEmpty($name, 'Store name should not be empty');
        }
    }

    public function testContentofGetListOfStoresInRegionExpanded(ApiTester $I): void
    {
        $regionTop = $I->createRegion(null, ['type' => UnitType::CITY], false);
        $I->addRegionMember($regionTop['id'], $this->user['id'], true);

        $regionChild1 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN], false);
        $store1 = $I->createStore($regionChild1['id']);
        $regionChild2 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN], false);
        $store2 = $I->createStore($regionChild2['id']);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionTop['id'] . '/stores', ['expand' => true]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $ids = $I->grabDataFromResponseByJsonPath('$.*.id');
        $I->assertContains($store1[self::ID], $ids);
        $I->assertContains($store2[self::ID], $ids);

        $names = $I->grabDataFromResponseByJsonPath('$.*.name');
        $I->assertContains($store1['name'], $names);
        $I->assertContains($store2['name'], $names);
    }

    public function canNotGetAccessToCreateStoreAsUnknownUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', $this->createDefaultNewStoreJson());
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    public function canNotGetAccessToCreateStoreAsFoodsharer(ApiTester $I): void
    {
        $I->login($this->foodsharer['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', $this->createDefaultNewStoreJson());
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canNotGetAccessToCreateStoreAsFoodsaver(ApiTester $I): void
    {
        $I->login($this->user['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', $this->createDefaultNewStoreJson());
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canNotGetAccessToCreateStoreAsUnverifiedFoodsaver(ApiTester $I): void
    {
        $I->login($this->unverifiedUser['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', $this->createDefaultNewStoreJson());
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canGetAccessToCreateStoreAsStoreManagerOfRegionWithoutContent(ApiTester $I): void
    {
        $I->login($this->manager['email']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', []);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);
    }

    public function createStoreAsStoreManagerOfValidRegionButInvalidContent(ApiTester $I): void
    {
        $storeUri = self::API_REGIONS . '/' . $this->region['id'] . '/stores';
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $goodStoreData = ['store' => [
            'name' => 'Store Name', 'regionId' => $this->region['id'],
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ], 'firstPost' => null];

        // No store data
        $I->sendPOST($storeUri, ['firstPost' => null]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // store is null
        $badStoreData = $goodStoreData;
        $badStoreData['store'] = null;
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // store name is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['name'] = null;
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // store name is empty
        $badStoreData = $goodStoreData;
        $badStoreData['store']['name'] = '';
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // location is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['location'] = null;
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // empty latitude
        $badStoreData = $goodStoreData;
        $badStoreData['store']['location']['lat'] = '';
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // bad longitute
        $badStoreData = $goodStoreData;
        $badStoreData['store']['location']['lon'] = 'sw';
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // missing street
        $badStoreData = $goodStoreData;
        unset($badStoreData['store']['street']);
        $I->sendPOST($storeUri, $badStoreData);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // street is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['street'] = null;
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // zipCode is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['zipCode'] = null;
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // city is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['city'] = null;
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // publicInfo is null
        $badStoreData = $goodStoreData;
        $badStoreData['store']['publicInfo'] = null;
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        // publicInfo is too long
        $badStoreData = $goodStoreData;
        $badStoreData['store']['publicInfo'] = implode('', array_fill(0, 521, '1'));
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);
    }

    public function createStoreAsStoreManagerWithPublicInfoXssIsBadRequest(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten <script>alert()</script>des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerWithNameXssIsBadRequest(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name <script>alert()</script>',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerWithStreetXssIsBadRequest(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name <script>alert()</script>',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg<script>alert()</script> 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe <script>alert()</script>',
            'publicInfo' => 'Wettendes es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerWithCityXssIsBadRequest(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name <script>alert()</script>',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe <script>alert()</script>',
            'publicInfo' => 'Wettendes es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerOfRegionForInvalidRegionIsForbidden(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 50.01, 'lon' => 10.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] + 10 . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerForWorkingGroupIsForbidden(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $group = $I->createWorkingGroup('WG', ['parent_id' => $this->region['id']], fillMailbox: false);

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 50.01, 'lon' => 10.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $group['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerOfRegionSuccessful(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 12.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::OK);
        $storeIds = $I->grabDataFromResponseByJsonPath('$.id');
        $I->assertEquals(1, count($storeIds));

        $I->seeInDatabase('fs_betrieb', [
            'id' => $storeIds[0],
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);
    }

    public function createStoreAsStoreManagerOfRegionSuccessfulWithFirstPost(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 12.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => 'First post']);
        $I->seeResponseCodeIs(Http::OK);
        $storeIds = $I->grabDataFromResponseByJsonPath('$.id');
        $I->assertEquals(1, count($storeIds));

        $I->seeInDatabase('fs_betrieb', [
            'id' => $storeIds[0],
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'],
            'str' => $storeInfo['street'],
            'plz' => $storeInfo['zipCode'],
            'stadt' => $storeInfo['city'],
            'public_info' => $storeInfo['publicInfo']]);

        $post = $I->grabEntryFromDatabase('fs_store_has_wallpost', ['store_id' => $storeIds[0]]);
        $I->seeInDatabase('fs_wallpost', [
            'id' => $post['wallpost_id'],
            'foodsaver_id' => $this->manager['id'],
            'body' => 'First post'
        ]);
    }

    public function patchStoreNameAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['name' => 'This is a nice store']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'name' => 'This is a nice store']);

        $teamConversationName = $I->grabFromDatabase('fs_conversation', 'name', ['id' => $this->store['team_conversation_id']]);
        $I->assertStringContainsString('This is a nice store', $teamConversationName);
        $sprinterConversationName = $I->grabFromDatabase('fs_conversation', 'name', ['id' => $this->store['springer_conversation_id']]);
        $I->assertStringContainsString('This is a nice store', $sprinterConversationName);
    }

    /**
     * @example {"field": "name", "value": "This is a nice store", "dbField":"name"}
     * @example {"field": "regionId", "value": "{{regionOfMember}}", "dbField":"bezirk_id"}
     * @example {"field": "regionId", "value": "{{regionWithoutMembership}}", "dbField":"bezirk_id"}
     * @example {"field": "publicInfo", "value": "This is a nice store", "dbField":"public_info"}
     * @example {"field": "publicTime", "value": 2, "dbField":"public_time"}
     * @example {"field": "categoryId", "value": 3, "dbField":"betrieb_kategorie_id"}
     * @example {"field": "chainId", "value": 4, "dbField":"kette_id"}
     * @example {"field": "cooperationStatus", "value": 2, "dbField":"betrieb_status_id"}
     * @example {"field": "description", "value": "Invalid", "dbField":"besonderheiten"}
     * @example {"field": "cooperationStart", "value": "2022-01-13", "dbField":"begin"}
     * @example {"field": "calendarInterval", "value": 2, "dbField":"prefetchtime"}
     * @example {"field": "weight", "value": 2, "dbField":"abholmenge"}
     * @example {"field": "effort", "value": 2, "dbField":"ueberzeugungsarbeit"}
     * @example {"field": "teamStatus", "value": 2, "dbField":"team_status"}
     * @example {"field": "location", "value": {"lat": 49.9}, "dbField":"lat"}
     * @example {"field": "location", "value": {"lon": 4.9}, "dbField":"lon"}
     * @example {"field": "address", "value": { "street": "Weberstrasse 123"}, "dbField":"str"}
     * @example {"field": "address", "value": { "city": "Berlin"}, "dbField":"stadt"}
     * @example {"field": "address", "value": { "zipCode": "12345"}, "dbField":"plz"}
     * @example {"field": "contact", "value": {"name": "Invalid"}, "dbField":"ansprechpartner"}
     * @example {"field": "contact", "value": {"phone": "Invalid"}, "dbField":"telefon"}
     * @example {"field": "contact", "value": {"fax": "Invalid"}, "dbField":"fax"}
     * @example {"field": "contact", "value": {"email": "Invalid"}, "dbField":"email"}
     * @example {"field": "showsSticker", "value": 1, "dbField":"sticker"}
     * @example {"field": "publicity", "value": 1, "dbField":"presse"}
     * @example {"field": "options", "value": { "useRegionPickupRule": true}, "dbField":"use_region_pickup_rule"}
     */
    public function patchStoreAsNormalUserReturnForbidden(ApiTester $I, Example $example): void
    {
        $value = $example['value'];
        if (is_string($value)) {
            if ($value == '{{regionOfMember}}') {
                $value = $this->region['id'];
            }
            if ($value == '{{regionWithoutMembership}}') {
                $value = $this->otherRegion['id'];
            }
        }

        $I->login($this->foodsharer[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->login($this->teamMember[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            $example['dbField'] => $this->store[$example['dbField']]]);
    }

    public function patchStoreGroceriesAsUserReturnForbidden(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['groceries' => [1, 2, 3]]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->assertEquals(0, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
    }

    /**
     * @example {"field": "name", "value": "This is a nice store", "dbField":"name"}
     * @example {"field": "regionId", "value": "{{regionOfMember}}", "dbField":"bezirk_id"}
     * @example {"field": "regionId", "value": "{{regionWithoutMembership}}", "dbField":"bezirk_id"}
     * @example {"field": "location", "value": {"lat": 49.9}, "dbField":"lat"}
     * @example {"field": "location", "value": {"lon": 4.9}, "dbField":"lon"}
     * @example {"field": "address", "value": { "street": "Weberstrasse 123"}, "dbField":"str"}
     * @example {"field": "address", "value": { "city": "Berlin"}, "dbField":"stadt"}
     * @example {"field": "address", "value": { "zipCode": "12345"}, "dbField":"plz"}
     * @example {"field": "publicInfo", "value": "This is a nice store", "dbField":"public_info"}
     * @example {"field": "publicTime", "value": 2, "dbField":"public_time"}
     * @example {"field": "categoryId", "value": 3, "dbField":"betrieb_kategorie_id"}
     * @example {"field": "chainId", "value": 4, "dbField":"kette_id"}
     * @example {"field": "cooperationStatus", "value": 2, "dbField":"betrieb_status_id"}
     * @example {"field": "description", "value": "Invalid", "dbField":"besonderheiten"}
     * @example {"field": "contact", "value": {"name": "Invalid"}, "dbField":"ansprechpartner"}
     * @example {"field": "contact", "value": {"phone": "Invalid"}, "dbField":"telefon"}
     * @example {"field": "contact", "value": {"fax": "Invalid"}, "dbField":"fax"}
     * @example {"field": "contact", "value": {"email": "Invalid"}, "dbField":"email"}
     * @example {"field": "cooperationStart", "value": "2022-01-13", "dbField":"begin"}
     * @example {"field": "calendarInterval", "value": 2, "dbField":"prefetchtime"}
     * @example {"field": "weight", "value": 2, "dbField":"abholmenge"}
     * @example {"field": "effort", "value": 2, "dbField":"ueberzeugungsarbeit"}
     * @example {"field": "teamStatus", "value": 2, "dbField":"team_status"}
     * @example {"field": "showsSticker", "value": 1, "dbField":"sticker"}
     * @example {"field": "publicity", "value": 1, "dbField":"presse"}
     * @example {"field": "options", "value": { "useRegionPickupRule": true}, "dbField":"use_region_pickup_rule"}
     */
    public function patchStoreAsUnknownUserReturnUnauthorized(ApiTester $I, Example $example): void
    {
        $value = $example['value'];
        if (is_string($value)) {
            if ($value == '{{regionOfMember}}') {
                $value = $this->region['id'];
            }
            if ($value == '{{regionWithoutMembership}}') {
                $value = $this->otherRegion['id'];
            }
        }

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            $example['dbField'] => $this->store[$example['dbField']]]);
    }

    public function cannotPatchRegionAsStoreManagerOfRegionsIfMembersAreNotPartOfNewRegion(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['regionId' => $this->nextRegion['id']]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'bezirk_id' => $this->region['id']]);
    }

    public function canPatchRegionAsStoreManagerOfRegionsIfMembersArePartOfNewRegion(ApiTester $I): void
    {
        $I->addRegionMember($this->nextRegion['id'], $this->teamMember[self::ID]);
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['regionId' => $this->nextRegion['id']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'bezirk_id' => $this->nextRegion['id']]);
    }

    /**
     * @example {"value": "A2345", "expected": "A2345"}
     * @example {"value": "  B6 ", "expected": "B6"}
     */
    public function patchStoreZipCodeAsStoreManager(ApiTester $I, Example $example): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['postalCode' => $example['value']]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'plz' => $example['expected']]);
    }

    public function canNotPatchStoreZipCodeWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['postalCode' => '01234567890']]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'plz' => $this->store['plz']]);
    }

    /**
     * @example {"value": "Store street 123", "expected": "Store street 123"}
     * @example {"value": "   Another street  45  ", "expected": "Another street  45"}
     */
    public function patchStoreStreetAsStoreManager(ApiTester $I, Example $example): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['street' => $example['value']]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'str' => $example['expected']]);
    }

    public function patchStoreGeoLocationLatAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['location' => ['lat' => 49.9]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lat' => 49.9]);
    }

    public function canNotPatchStoreGeoLocationLatWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['location' => ['lat' => 'a123']]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lat' => $this->store['lat']]);
    }

    public function patchStoreGeoLocationLonAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['location' => ['lon' => 49.9]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lon' => 49.9]);
    }

    public function canNotPatchStoreGeoLocationLonWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['location' => ['lon' => 'a123']]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lon' => $this->store['lon']]);
    }

    public function patchStorePublicInformationAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicInfo' => 'Test']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_info' => 'Test']);
    }

    public function canNotPatchStorePublicInformationWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');

        // null
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicInfo' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        // too long
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicInfo' => implode('', array_fill(0, 521, '1'))]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_info' => $this->store['public_info']]);
    }

    public function canNotPatchStorePublicInformationWithXssForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicInfo' => 'Wetten <script>alert()</script>des es geht']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_info' => $this->store['public_info']]);
    }

    public function patchStorePublicTimeAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => 2]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_time' => 2]);
    }

    public function canNotPatchStorePublicTimeWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => 'hallo']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => 'a2']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => 5]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicTime' => implode('', array_fill(0, 201, '1'))]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_time' => $this->store['public_time']]);
    }

    public function patchStoreCategoryAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 2, 'name' => 'Category', 'type' => 0]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 2]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => 2]);
    }

    public function patchStoreCategoryWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => $this->store['betrieb_kategorie_id']]);
    }

    public function canNotPatchStoreCategoryWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 'hallo']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 'a2']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => 5]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['categoryId' => implode('', array_fill(0, 201, '1'))]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => $this->store['betrieb_kategorie_id']]);
    }

    public function StoreChainDataAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveInDatabase('fs_chain', ['id' => 4, 'name' => 'Chain']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['chainId' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => 4]);
    }

    public function StoreChainDataWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['chainId' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => $this->store['kette_id']]);
    }

    public function canNotStoreChainDataWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['chainId' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['chainId' => 'hallo']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['chainId' => 'a2']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => $this->store['kette_id']]);
    }

    public function patchStoreCooperationStatusAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStatus' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => 4]);
    }

    public function patchStoreCooperationStatusWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStatus' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => $this->store['betrieb_status_id']]);
    }

    public function canNotPatchStoreCooperationStatusWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStatus' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStatus' => 'hallo']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStatus' => 'a2']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => $this->store['betrieb_status_id']]);
    }

    public function patchStoreDescriptionAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['description' => 'Store description']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'besonderheiten' => 'Store description']);
    }

    public function patchStoreContactNameAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['name' => 'Store contactName']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ansprechpartner' => 'Store contactName']);
    }

    public function canNotPatchStoreContactNameWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['name' => implode('', array_fill(0, 60 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ansprechpartner' => $this->store['ansprechpartner']]);
    }

    public function patchStoreContactPhoneAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['phone' => '+49 123 123456']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'telefon' => '+49 123 123456']);
    }

    public function canNotPatchStoreContactPhoneWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['phone' => implode('', array_fill(0, 50 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'telefon' => $this->store['telefon']]);
    }

    public function patchStoreContactFaxAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['fax' => 'Store contactFax']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'fax' => 'Store contactFax']);
    }

    public function canNotPatchStoreContactFaxWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['fax' => implode('', array_fill(0, 50 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'fax' => $this->store['fax']]);
    }

    public function patchStoreContactEMailAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['email' => 'Store contactEmail']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'email' => 'Store contactEmail']);
    }

    public function canNotPatchStoreContactEMailWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['contact' => ['email' => implode('', array_fill(0, 60 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'email' => $this->store['email']]);
    }

    public function patchStoreCooperationStartAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => '2022-04-13']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'begin' => '2022-04-13']);
    }

    public function canNotPatchStoreCooperationStartWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => '']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => 'Hallo']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => '1-2-2']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => 'A1-A23-123']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['cooperationStart' => '12.01.2022']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'begin' => $this->store['begin']]);
    }

    public function patchStoreCalendarIntervalAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['calendarInterval' => 604800]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'prefetchtime' => 604800]);

        // Used for store vacation when the store contains many automatic pickup roles
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['calendarInterval' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
                'id' => $this->store[self::ID],
                'prefetchtime' => 0]);
    }

    public function canNotPatchStoreCalendarIntervalWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['calendarInterval' => 10_000_000_001]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['calendarInterval' => 'a']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['calendarInterval' => '0.1']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'prefetchtime' => $this->store['prefetchtime']]);
    }

    public function patchStoreWeightAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['weight' => 1]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'abholmenge' => 1]);
    }

    public function canNotPatchStoreWeightWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['weight' => 9]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['weight' => 'a']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['weight' => '0.1']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);
        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'abholmenge' => $this->store['abholmenge']]);
    }

    public function patchStoreTeamStatusOk(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['teamStatus' => 2]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeInDatabase('fs_betrieb', ['id' => $this->store[self::ID], 'team_status' => 2]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['teamStatus' => 1]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeInDatabase('fs_betrieb', ['id' => $this->store[self::ID], 'team_status' => 1]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['teamStatus' => 0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeInDatabase('fs_betrieb', ['id' => $this->store[self::ID], 'team_status' => 0]);
    }

    public function patchStoreEffortAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['effort' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => 4]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['effort' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => 0]);
    }

    public function canNotPatchStoreEffortWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['effort' => 5]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => $this->store['ueberzeugungsarbeit']]);
    }

    public function patchStoreOptionUseRegionPickupRuleAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['options' => ['useRegionPickupRule' => true]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'use_region_pickup_rule' => 1]);
    }

    public function canNotPatchStoreOptionUseRegionPickupRuleWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['options' => ['useRegionPickupRule' => 'A']]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['options' => ['useRegionPickupRule' => 1]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['options' => ['useRegionPickupRule' => 0]]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'use_region_pickup_rule' => $this->store['use_region_pickup_rule']]);
    }

    public function patchStoreOptionShowsStickerAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['showsSticker' => 1]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => 1]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['showsSticker' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => 0]);
    }

    public function canNotPatchStoreShowsStickerWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['showsSticker' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => $this->store['sticker']]);
    }

    public function patchStorePublicityAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicity' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => 0]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicity' => 1]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => 1]);
    }

    public function canNotPatchStorePublicityWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicity' => 3]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicity' => true]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['publicity' => 'A']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => $this->store['presse']]);
    }

    public function patchStoreTeamStatusNotFound(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] + 1 . '/details', ['teamStatus' => 2]);
        $I->seeResponseCodeIs(Http::NOT_FOUND);
    }

    public function patchStoreTeamStatusInvalid(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['teamStatus' => 'a']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['teamStatus' => 3]);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);
    }

    /**
     * @example {"value": "Store town", "expected": "Store town"}
     * @example {"value": "   Another  town   ", "expected": "Another  town"}
     */
    public function patchStoreCityAsStoreManager(ApiTester $I, Example $example): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['city' => $example['value']]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => $example['expected']]);
    }

    public function canNotPatchStoreCityWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => 'notAObject']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['city' => 123]]); // Wrong type
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => $this->store['stadt']]);
    }

    public function canNotPatchStoreCityAsUnknownUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['city' => 'This is a nice store']]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => $this->store['stadt']]);
    }

    public function patchStoreGroceriesAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['groceries' => [1, 2, 3]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->assertEquals(3, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 1]);
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 2]);
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 3]);
    }

    public function canNotPatchStoreGroceriesWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['groceries' => 'String is invalid']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['groceries' => '123']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['groceries' => '1, 2, 3']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->assertEquals(0, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
    }

    public function canNotPatchStoreGroceriesAsUnknownUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/details', ['address' => ['city' => 'This is a nice store']]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->assertEquals(0, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
    }

    public function cannotApplyWithEmptyProfile(ApiTester $I): void
    {
        // Create an unverified user with empty profile
        $foodsaver = $I->createFoodsaver(
            null,
            []
        );
        $I->login($foodsaver['email']);
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => false]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => false]);
        $I->seeResponseContainsJson(['hasHomeRegion' => false]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => false]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }

    public function cannotApplyWithoutHomeRegion(ApiTester $I): void
    {
        // Create new user with complete profile
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg'
            ]
        );
        $I->login($foodsaver['email']);

        // Can still not apply because region is missing
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => false]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => false]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => false]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }

    public function cannotApplyWithoutBeingMemberOfStoreRegion(ApiTester $I): void
    {
        // Create new user with complete profile having home region which is not
        // the store's region
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg',
                'bezirk_id' => $this->otherRegion['id']
            ]
        );
        $I->login($foodsaver['email']);

        // Can still not apply because not in region of store
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => false]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => true]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => false]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }

    public function canApplyWithProfileAndRegion(ApiTester $I): void
    {
        // Create new user with complete profile and in region of store
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg',
                'bezirk_id' => $this->region['id']
            ]
        );
        $I->login($foodsaver['email']);

        // Can apply with default settings for store (no verification and no
        // phone enforced)
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => true]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => true]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => true]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }

    public function cannotApplyWithoutVerificationIfEnforced(ApiTester $I): void
    {
        // Enforce verification for store application (temporarily log in as store manager)
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store['id'] . '/details', ['isVerifiedRequired' => true]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'verified_requirement' => true
        ]);

        // Create new user with complete profile and in region of store but not verified and no phone
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg',
                'bezirk_id' => $this->region['id'],
                'verified' => 0
            ]
        );
        $I->login($foodsaver['email']);
        // Can not apply for store when foodsaver is not verified but this is a
        // requirement
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => false]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => true]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => true]);
        $I->seeResponseContainsJson(['requireVerification' => true]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }

    public function cannotApplyWithoutPhoneIfEnforced(ApiTester $I): void
    {
        // Enforce phone for store application (temporarily log in as store manager)
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store['id'] . '/details', ['isPhoneRequired' => true]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'phone_requirement' => true
        ]);

        // Create new user with complete profile and in region of store without phone
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg',
                'telefon' => '',
                'handy' => '',
                'bezirk_id' => $this->region['id'],
                'verified' => 1
            ]
        );
        $I->login($foodsaver['email']);
        // Can not apply for store when foodsaver has no phone but this is a
        // requirement
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => false]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => true]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => true]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => true]);
    }

    public function canApplyWithEnforcing(ApiTester $I): void
    {
        // Enforce verification and phone for store application (temporarily log
        // in as store manager)
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store['id'] . '/details', [
            'isPhoneRequired' => true,
            'isVerifiedRequired' => true
        ]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'phone_requirement' => true
        ]);

        // Create new user with all requirements met
        $foodsaver = $I->createFoodsaver(
            null,
            [
                'name' => 'fs1',
                'nachname' => 'saver1',
                'geb_datum' => '1990-01-01',
                'photo' => 'does-not-exist.jpg',
                'handy' => '+4966669999',
                'bezirk_id' => $this->region['id'],
                'verified' => 1
            ]
        );
        $I->login($foodsaver['email']);
        // Can apply for store
        $I->sendGET(self::API_MAP_STORES . '/' . $this->store['id']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['maySendRequest' => true]);
        $I->seeResponseContainsJson(['hasCompleteProfile' => true]);
        $I->seeResponseContainsJson(['hasHomeRegion' => true]);
        $I->seeResponseContainsJson(['isMemberOfRegion' => true]);
        $I->seeResponseContainsJson(['requireVerification' => false]);
        $I->seeResponseContainsJson(['requirePhone' => false]);
    }
}
