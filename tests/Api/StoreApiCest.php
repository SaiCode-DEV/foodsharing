<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Faker;
use Foodsharing\Modules\Core\DBConstants\Store\Milestone;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

/**
 * Tests for the store api.
 */
class StoreApiCest
{
    private $store;
    private $foodsharer;
    private $user;
    private $unverifiedUser;
    private $teamMember;
    private $manager;
    private $orga;
    private $region;
    private $otherRegion;
    private $nextRegion;
    private $faker;

    private const API_STORES = 'api/stores';
    private const API_REGIONS = 'api/region';
    private const EMAIL = 'email';
    private const ID = 'id';

    public function createDefaultNewStoreJson(): array
    {
        return ['store' => [
            'name' => 'Store Name', 'regionId' => $this->region['id'],
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
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
        $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 20, 'name' => 'Category']);
        $this->foodsharer = $I->createFoodsharer(null, ['verified' => 0]);
        $this->user = $I->createFoodsaver(null, ['verified' => 0]);
        $this->unverifiedUser = $I->createFoodsaver(null, ['verified' => 0]);
        $this->teamMember = $I->createFoodsaver();
        $this->manager = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $this->orga = $I->createOrga();
        $this->teamConversation = $I->createConversation([$this->manager['id'], $this->teamMember['id']]);
        $this->springerConversation = $I->createConversation([$this->manager['id'], $this->teamMember['id']]);
        $this->store = $I->createStore($this->region['id'], $this->teamConversation['id'], $this->springerConversation['id'], ['kette_id' => 40, 'betrieb_kategorie_id' => 20, 'use_region_pickup_rule' => 1]);

        $I->addStoreTeam($this->store[self::ID], $this->teamMember[self::ID], false);

        $I->addRegionMember($this->nextRegion['id'], $this->manager['id']);
        $I->addStoreTeam($this->store[self::ID], $this->manager[self::ID], true);
        $this->faker = Faker\Factory::create('de_DE');
    }

    public function canNotGetAccessToGetStoreAsUnknownUser(ApiTester $I)
    {
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/information');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    private function createGetStoreAsFoodsaverJson()
    {
        return [
            'id' => $this->store['id'],
            'name' => $this->store['name'],
            'region' => [
                'id' => $this->store['bezirk_id']
            ],
            'location' => [
                'lon' => $this->store['lon'],
                'lat' => $this->store['lat']
            ],
            'category' => [
                'id' => $this->store['betrieb_kategorie_id']
            ],
            'cooperationStatus' => $this->store['betrieb_status_id'],
            'teamStatus' => $this->store['team_status'],
            'chain' => [
                'id' => $this->store['kette_id']
            ],
            'publicInfo' => $this->store['public_info'],
            'publicTime' => $this->store['public_time'],
            'cooperationStart' => $this->store['begin'],
            'calendarInterval' => $this->store['prefetchtime'],
            'weight' => $this->store['abholmenge'],
            'createdAt' => $this->store['added'],
            'address' => [
                'street' => $this->store['str'],
                'city' => $this->store['stadt'],
                'zipCode' => $this->store['plz']
            ]
        ];
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
                'zipCode' => 'string'
            ]
        ];
    }

    private function createGetStoreAsTeamMemberJsonType($store = [], $notExpected = false)
    {
        $store['publicity'] = $notExpected ? 'null' : 'boolean|null';
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
        $store['publicity'] = $this->store['presse'] == 1;
        $store['options'] = [
            'useRegionPickupRule' => $this->store['use_region_pickup_rule'] == 1
        ];

        return $store;
    }

    private function createGetStoreAsStoreManagerJsonType($store = [], $notExpected = false)
    {
        $store['effort'] = $notExpected ? 'null' : 'integer|null';
        $store['updatedAt'] = $notExpected ? 'null' : 'string|null';
        $store['showsSticker'] = $notExpected ? 'null' : 'boolean|null';
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
        $store['showsSticker'] = $this->store['sticker'] == 1;
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
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] + 1 . '/information');
        $I->seeResponseCodeIs(Http::NOT_FOUND);
    }

    public function getAccessToGetStoreInformationAsUnVerifiedFoodsaver(ApiTester $I)
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/information');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->createGetStoreAsFoodsaverJson());

        $storeType = $this->createGetStoreAsFoodsaverJsonTypes();
        $storeType = $this->createGetStoreAsTeamMemberJsonType($storeType, true);
        $storeType = $this->createGetStoreAsStoreManagerJsonType($storeType, true);
        $I->seeResponseMatchesJsonType($storeType);
    }

    public function getAccessToGetStoreInformationAsFoodsaver(ApiTester $I)
    {
        $this->getAccessToGetStoreInformationAsUnVerifiedFoodsaver($I);
    }

    public function getAccessToGetStoreInformationAsStoreManager(ApiTester $I)
    {
        $I->login($this->manager[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/information');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->createGetStoreAsFoodsaverJson());

        $storeType = $this->createGetStoreAsFoodsaverJsonTypes();
        $storeType = $this->createGetStoreAsTeamMemberJsonType($storeType, false);
        $storeType = $this->createGetStoreAsStoreManagerJsonType($storeType, false);
        $I->seeResponseMatchesJsonType($storeType);
    }

    public function getAccessToGetStoreInformationAsOrga(ApiTester $I)
    {
        $I->login($this->orga[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/information');
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
        $I->sendGET(self::API_STORES . '/' . $this->store['id'] . '/information');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->createGetStoreAsFoodsaverJson());
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
        $I->seeResponseContainsJson(['maxCountPickupSlot' => 10]);
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
        $I->seeResponseContainsJson(['maxCountPickupSlot' => 10]);
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
        $regionTop = $I->createRegion(null, ['type' => UnitType::CITY]);
        $I->addRegionMember($regionTop['id'], $this->user['id'], true);

        $regionChild1 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store1 = $I->createStore($regionChild1['id']);
        $regionChild2 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store2 = $I->createStore($regionChild2['id']);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionTop['id'] . '/stores');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $ids = $I->grabDataFromResponseByJsonPath('stores.*.id');
        $I->assertContains($store1[self::ID], $ids);
        $I->assertContains($store2[self::ID], $ids);

        $names = $I->grabDataFromResponseByJsonPath('stores.*.name');
        foreach ($names as $name) {
            $I->assertNotEmpty($name, 'Store name should not be empty');
        }
    }

    public function testContentofGetListOfStoresInRegionExpanded(ApiTester $I): void
    {
        $regionTop = $I->createRegion(null, ['type' => UnitType::CITY]);
        $I->addRegionMember($regionTop['id'], $this->user['id'], true);

        $regionChild1 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store1 = $I->createStore($regionChild1['id']);
        $regionChild2 = $I->createRegion(null, ['parent_id' => $regionTop['id'], 'type' => UnitType::PART_OF_TOWN]);
        $store2 = $I->createStore($regionChild2['id']);

        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_REGIONS . '/' . $regionTop['id'] . '/stores', ['expand' => true]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $ids = $I->grabDataFromResponseByJsonPath('stores.*.id');
        $I->assertContains($store1[self::ID], $ids);
        $I->assertContains($store2[self::ID], $ids);

        $names = $I->grabDataFromResponseByJsonPath('stores.*.name');
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
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
    }

    public function createStoreAsStoreManagerOfValidRegionButInvalidContent(ApiTester $I): void
    {
        $storeUri = self::API_REGIONS . '/' . $this->region['id'] . '/stores';
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        // No store data
        $I->sendPOST($storeUri, ['firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->sendPOST($storeUri, ['store' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        // Empty store name
        $I->sendPOST($storeUri, ['store' => ['name' => ''], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        // Empty location
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => null
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => '']
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => '123.01', 'lon' => 'sw']
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => 123.01, 'lon' => 4.190000]
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => 123.01, 'lon' => 4.190000],
                'street' => null,
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => 123.01, 'lon' => 4.190000],
                'street' => 'Mühlbachweg 122',
                'zipCode' => null
            ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->sendPOST($storeUri, [
            'store' => [
                'name' => 'Store Name',
                'location' => ['lat' => 123.01, 'lon' => 4.190000],
                'street' => 'Mühlbachweg 122',
                'zipCode' => '12234',
                'city' => null
            ], 'firstPost' => null]);
        $I->sendPOST($storeUri, [
                'store' => [
                    'name' => 'Store Name',
                    'location' => ['lat' => 123.01, 'lon' => 4.190000],
                    'street' => 'Mühlbachweg 122',
                    'zipCode' => '12234',
                    'city' => 'Karlsruhe',
                    'publicInfo' => null
                ], 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
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
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
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
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
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
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
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
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->dontSeeInDatabase('fs_betrieb', [
            'name' => $storeInfo['name'],
            'bezirk_id' => $this->region['id'] + 10,
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
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
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

    public function createStoreAsStoreManagerOfRegionSuccessful(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => null]);
        $I->seeResponseCodeIs(Http::CREATED);
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

        $I->dontSeeInDatabase('fs_betrieb_notiz', [
            'foodsaver_id' => $this->manager['id'],
            'betrieb_id' => $storeIds[0],
            'milestone' => Milestone::NONE]);
    }

    public function createStoreAsStoreManagerOfRegionSuccessfulWithFirstPost(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $storeInfo = [
            'name' => 'Store Name',
            'location' => ['lat' => 123.01, 'lon' => 4.190000],
            'street' => 'Mühlbachweg 122',
            'zipCode' => '12234',
            'city' => 'Karlsruhe',
            'publicInfo' => 'Wetten des es geht'
        ];
        $I->sendPOST(self::API_REGIONS . '/' . $this->region['id'] . '/stores', [
            'store' => $storeInfo, 'firstPost' => 'First post']);
        $I->seeResponseCodeIs(Http::CREATED);
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

        $I->seeInDatabase('fs_betrieb_notiz', [
            'foodsaver_id' => $this->manager['id'],
            'betrieb_id' => $storeIds[0],
            'milestone' => Milestone::NONE,
            'text' => 'First post']);
    }

    public function getStore(ApiTester $I): void
    {
        $I->login($this->teamMember[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['id' => $this->store[self::ID]]);
        $I->seeResponseContainsJson(['phone' => $this->store['telefon']]);
    }

    public function canOnlyAccessStoreAsFoodsaver(ApiTester $I): void
    {
        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->login($this->foodsharer[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canOnlySeeStoreDetailsAsMember(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseContainsJson(['id' => $this->store[self::ID]]);
        $I->dontSeeResponseContainsJson(['phone' => $this->store['telefon']]);
    }

    public function patchStoreNameAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['name' => 'This is a nice store']);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'name' => 'This is a nice store']]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'name' => 'This is a nice store']);

        $teamConversationName = $I->grabFromDatabase('fs_conversation', 'name', ['id' => $this->teamConversation['id']]);
        $I->assertStringContainsString('This is a nice store', $teamConversationName);
        $sprinterConversationName = $I->grabFromDatabase('fs_conversation', 'name', ['id' => $this->springerConversation['id']]);
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
     * @example {"field": "showsSticker", "value": true, "dbField":"sticker"}
     * @example {"field": "publicity", "value": true, "dbField":"presse"}
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
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->login($this->teamMember[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            $example['dbField'] => $this->store[$example['dbField']]]);
    }

    public function patchStoreGroceriesAsUserReturnForbidden(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['groceries' => [1, 2, 3]]);
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
     * @example {"field": "showsSticker", "value": true, "dbField":"sticker"}
     * @example {"field": "publicity", "value": true, "dbField":"presse"}
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
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', [$example['field'] => $value]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            $example['dbField'] => $this->store[$example['dbField']]]);
    }

    public function patchRegionAsStoreManagerOfRegions(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['regionId' => $this->nextRegion['id']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'group' => ['id' => $this->nextRegion['id']]]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'bezirk_id' => $this->nextRegion['id']]);
    }

    public function patchStoreZipCodeAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['zipCode' => 'A2345']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'address' => ['postalCode' => 'A2345']]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'plz' => 'A2345']);
    }

    public function canNotPatchStoreZipCodeWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['zipCode' => '123456']]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'plz' => $this->store['plz']]);
    }

    public function patchStoreStreetAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['street' => 'Store street 123']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'address' => ['street' => 'Store street 123']]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'str' => 'Store Street 123']);
    }

    public function patchStoreGeoLocationLatAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['location' => ['lat' => 49.9]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'lat' => 49.9]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lat' => 49.9]);
    }

    public function canNotPatchStoreGeoLocationLatWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['location' => ['lat' => 'a123']]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lat' => $this->store['lat']]);
    }

    public function patchStoreGeoLocationLonAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['location' => ['lon' => 49.9]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'lon' => 49.9]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lon' => 49.9]);
    }

    public function canNotPatchStoreGeoLocationLonWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['location' => ['lon' => 'a123']]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'lon' => $this->store['lon']]);
    }

    public function patchStorePublicInformationAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicInfo' => 'Test']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_info' => 'Test']);
    }

    public function canNotPatchStorePublicInformationWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicInfo' => implode('', array_fill(0, 201, '1'))]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_info' => $this->store['public_info']]);
    }

    public function patchStorePublicTimeAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => 2]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_time' => 2]);
    }

    public function canNotPatchStorePublicTimeWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => 'hallo']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => 'a2']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => 5]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicTime' => implode('', array_fill(0, 201, '1'))]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'public_time' => $this->store['public_time']]);
    }

    public function patchStoreCategoryAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveInDatabase('fs_betrieb_kategorie', ['id' => 2, 'name' => 'Category']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 2]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => 2]);
    }

    public function patchStoreCategoryWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => $this->store['betrieb_kategorie_id']]);
    }

    public function canNotPatchStoreCategoryWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 'hallo']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 'a2']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => 5]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['categoryId' => implode('', array_fill(0, 201, '1'))]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_kategorie_id' => $this->store['betrieb_kategorie_id']]);
    }

    public function patchStoreChainAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveInDatabase('fs_chain', ['id' => 4, 'name' => 'Chain']);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['chainId' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => 4]);
    }

    public function patchStoreChainWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['chainId' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => $this->store['kette_id']]);
    }

    public function canNotPatchStoreChainWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['chainId' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['chainId' => 'hallo']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['chainId' => 'a2']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'kette_id' => $this->store['kette_id']]);
    }

    public function patchStoreCooperationStatusAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStatus' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => 4]);
    }

    public function patchStoreCooperationStatusWithInvalidAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStatus' => 200]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => $this->store['betrieb_status_id']]);
    }

    public function canNotPatchStoreCooperationStatusWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStatus' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStatus' => 'hallo']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStatus' => 'a2']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'betrieb_status_id' => $this->store['betrieb_status_id']]);
    }

    public function patchStoreDescriptionAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['description' => 'Store description']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'besonderheiten' => 'Store description']);
    }

    public function patchStoreContactNameAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['name' => 'Store contactName']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ansprechpartner' => 'Store contactName']);
    }

    public function canNotPatchStoreContactNameWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['name' => implode('', array_fill(0, 60 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ansprechpartner' => $this->store['ansprechpartner']]);
    }

    public function patchStoreContactPhoneAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['phone' => '+49 123 123456']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'telefon' => '+49 123 123456']);
    }

    public function canNotPatchStoreContactPhoneWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['phone' => implode('', array_fill(0, 50 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'telefon' => $this->store['telefon']]);
    }

    public function patchStoreContactFaxAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['fax' => 'Store contactFax']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'fax' => 'Store contactFax']);
    }

    public function canNotPatchStoreContactFaxWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['fax' => implode('', array_fill(0, 50 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'fax' => $this->store['fax']]);
    }

    public function patchStoreContactEMailAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['email' => 'Store contactEmail']]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'email' => 'Store contactEmail']);
    }

    public function canNotPatchStoreContactEMailWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['contact' => ['email' => implode('', array_fill(0, 60 + 1, '1'))]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'email' => $this->store['email']]);
    }

    public function patchStoreCooperationStartAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => '2022-04-13']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'begin' => '2022-04-13']);
    }

    public function canNotPatchStoreCooperationStartWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => '']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => 'Hallo']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => '1-2-2']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => 'A1-A23-123']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['cooperationStart' => '12.01.2022']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'begin' => $this->store['begin']]);
    }

    public function patchStoreCalendarIntervalAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['calendarInterval' => 604800]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'prefetchtime' => 604800]);

        // Used for store vacation when the store contains many automatic pickup roles
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['calendarInterval' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
                'id' => $this->store[self::ID],
                'prefetchtime' => 0]);
    }

    public function canNotPatchStoreCalendarIntervalWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['calendarInterval' => 10000000001]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['calendarInterval' => 'a']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['calendarInterval' => '0.1']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'prefetchtime' => $this->store['prefetchtime']]);
    }

    public function patchStoreWeightAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['weight' => 1]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'abholmenge' => 1]);
    }

    public function canNotPatchStoreWeightWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['weight' => 8]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['weight' => 'a']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['weight' => '0.1']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'abholmenge' => $this->store['abholmenge']]);
    }

    public function patchStoreTeamStatusOk(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['teamStatus' => 2]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'teamStatus' => 2]]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['teamStatus' => 1]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'teamStatus' => 1]]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['teamStatus' => 0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'teamStatus' => 0]]);
    }

    public function patchStoreEffortAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['effort' => 4]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => 4]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['effort' => 0]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => 0]);
    }

    public function canNotPatchStoreEffortWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['effort' => 5]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'ueberzeugungsarbeit' => $this->store['ueberzeugungsarbeit']]);
    }

    public function patchStoreOptionUseRegionPickupRuleAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['options' => ['useRegionPickupRule' => true]]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'use_region_pickup_rule' => 1]);
    }

    public function canNotPatchStoreOptionUseRegionPickupRuleWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['options' => ['useRegionPickupRule' => 'A']]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['options' => ['useRegionPickupRule' => 1]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['options' => ['useRegionPickupRule' => 0]]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'use_region_pickup_rule' => $this->store['use_region_pickup_rule']]);
    }

    public function patchStoreOptionShowsStickerAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['showsSticker' => true]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => 1]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['showsSticker' => false]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => 0]);
    }

    public function canNotPatchStoreShowsStickerWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['showsSticker' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'sticker' => $this->store['sticker']]);
    }

    public function patchStorePublicityAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicity' => false]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => 0]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicity' => true]);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => 1]);
    }

    public function canNotPatchStorePublicityWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicity' => 1]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['publicity' => 'A']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'presse' => $this->store['presse']]);
    }

    public function patchStoreTeamStatusNotFound(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] + 1 . '/information', ['teamStatus' => 2]);
        $I->seeResponseCodeIs(Http::NOT_FOUND);
    }

    public function patchStoreTeamStatusInvalid(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['teamStatus' => 'a']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['teamStatus' => 3]);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);
    }

    public function patchStoreCityAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['city' => 'Store town 123']]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['store' => ['id' => $this->store[self::ID], 'address' => ['city' => 'Store town 123']]]);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => 'Store town 123']);
    }

    public function canNotPatchStoreCityWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => 'notAObject']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['city' => 123]]); // Wrong type
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => $this->store['stadt']]);
    }

    public function canNotPatchStoreCityAsUnknownUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['city' => 'This is a nice store']]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store[self::ID],
            'stadt' => $this->store['stadt']]);
    }

    public function patchStoreGroceriesAsStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['groceries' => [1, 2, 3]]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        $I->assertEquals(3, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 1]);
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 2]);
        $I->seeInDatabase('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID], 'lebensmittel_id' => 3]);
    }

    public function canNotPatchStoreGroceriesWithInvalidFormatForStoreManager(ApiTester $I): void
    {
        $I->login($this->manager[self::EMAIL]);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['groceries' => 'String is invalid']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['groceries' => '123']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['groceries' => '1, 2, 3']);
        $I->seeResponseCodeIs(Http::BAD_REQUEST);

        $I->assertEquals(0, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
    }

    public function canNotPatchStoreGroceriesAsUnknownUser(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPATCH(self::API_STORES . '/' . $this->store[self::ID] . '/information', ['address' => ['city' => 'This is a nice store']]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->assertEquals(0, $I->grabNumRecords('fs_betrieb_has_lebensmittel', ['betrieb_id' => $this->store[self::ID]]));
    }

    public function canWriteStoreWallpostAndGetAllPosts(ApiTester $I): void
    {
        $I->login($this->teamMember[self::EMAIL]);
        $newWallPost = $this->faker->realText(200);
        $I->sendPOST(self::API_STORES . '/' . $this->store[self::ID] . '/posts', ['text' => $newWallPost]);

        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeInDatabase('fs_betrieb_notiz', [
            'foodsaver_id' => $this->teamMember[self::ID],
            'betrieb_id' => $this->store[self::ID],
            'text' => $newWallPost,
        ]);

        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID] . '/posts');

        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['text' => $newWallPost]);
    }

    public function noStoreWallIfNotInTeam(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);

        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID] . '/posts');

        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->sendPOST(self::API_STORES . '/' . $this->store[self::ID] . '/posts', ['text' => 'Lorem ipsum.']);

        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function noStoreWallIfNotLoggedIn(ApiTester $I): void
    {
        $I->sendGET(self::API_STORES . '/' . $this->store[self::ID] . '/posts');

        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->sendPOST(self::API_STORES . '/' . $this->store[self::ID] . '/posts', ['text' => 'Lorem ipsum.']);

        $I->seeResponseCodeIs(Http::UNAUTHORIZED);
    }

    /**
     * All team members can remove their own posts at any time.
     */
    public function canRemoveOwnStorePost(ApiTester $I): void
    {
        $wallPost = [
            'betrieb_id' => $this->store[self::ID],
            'foodsaver_id' => $this->teamMember[self::ID],
            'text' => $this->faker->realText(100),
            'zeit' => $this->faker->dateTimeBetween('-14 days', '-30m')->format('Y-m-d H:i:s'),
            'milestone' => Milestone::NONE,
        ];
        $postId = $I->haveInDatabase('fs_betrieb_notiz', $wallPost);

        $I->login($this->teamMember[self::EMAIL]);

        $I->sendDELETE(self::API_STORES . '/' . $this->store[self::ID] . '/posts/' . $postId);

        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();
        $I->dontSeeInDatabase('fs_betrieb_notiz', ['id' => $postId]);

        $I->seeInDatabase('fs_store_log', [
            'store_id' => $this->store[self::ID],
            'fs_id_a' => $this->teamMember[self::ID],
            'fs_id_p' => $this->teamMember[self::ID],
            'content' => $wallPost['text'],
            'date_reference' => $wallPost['zeit'],
            'action' => StoreLogAction::DELETED_FROM_WALL,
        ]);
    }

    /**
     * Store managers can remove posts by others if they are older than 1 month.
     */
    public function storeManagerCanRemoveOldStorePost(ApiTester $I): void
    {
        $wallPost = [
            'betrieb_id' => $this->store[self::ID],
            'foodsaver_id' => $this->teamMember[self::ID],
            'text' => $this->faker->realText(100),
            'zeit' => $this->faker->dateTimeBetween('-66 days', '-33 days')->format('Y-m-d H:i:s'),
            'milestone' => Milestone::NONE,
        ];
        $postId = $I->haveInDatabase('fs_betrieb_notiz', $wallPost);

        $I->login($this->manager[self::EMAIL]);

        $I->sendDELETE(self::API_STORES . '/' . $this->store[self::ID] . '/posts/' . $postId);

        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_betrieb_notiz', ['id' => $postId]);

        $I->seeInDatabase('fs_store_log', [
            'store_id' => $this->store[self::ID],
            'fs_id_a' => $this->manager[self::ID],
            'fs_id_p' => $this->teamMember[self::ID],
            'content' => $wallPost['text'],
            'date_reference' => $wallPost['zeit'],
            'action' => StoreLogAction::DELETED_FROM_WALL,
        ]);
    }

    public function storeManagerCanRemoveNewStorePost(ApiTester $I): void
    {
        $wallPost = [
            'betrieb_id' => $this->store[self::ID],
            'foodsaver_id' => $this->teamMember[self::ID],
            'text' => $this->faker->realText(100),
            'zeit' => $this->faker->dateTimeBetween('-14 days', '-30m')->format('Y-m-d H:i:s'),
            'milestone' => Milestone::NONE,
        ];
        $postId = $I->haveInDatabase('fs_betrieb_notiz', $wallPost);

        $I->login($this->manager[self::EMAIL]);

        $I->sendDELETE(self::API_STORES . '/' . $this->store[self::ID] . '/posts/' . $postId);

        $I->seeResponseCodeIs(Http::OK);
        $I->dontSeeInDatabase('fs_betrieb_notiz', ['id' => $postId]);
    }
}
