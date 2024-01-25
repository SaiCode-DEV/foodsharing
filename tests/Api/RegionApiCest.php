<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Tests\Support\ApiTester;

class RegionApiCest
{
    private $user;
    private $userAmbassador;
    private $userOrga;
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->tester = $I;
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();
        $this->region = $I->createRegion();

        $this->userAmbassador = $I->createFoodsaver();
        $I->addRegionMember($this->region['id'], $this->userAmbassador['id']);
        $I->addRegionAdmin($this->region['id'], $this->userAmbassador['id']);
    }

    public function canJoinRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
        $I->sendPOST('api/region/' . $this->region['id'] . '/join');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function joinNotExistingRegionIs404(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/999999999/join');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseIsJson();
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => 999999999
            ]);
    }

    public function canNotJoinRegionAsFoodsharer(ApiTester $I): void
    {
        $foodsharer = $I->createFoodsharer();
        $I->login($foodsharer['email']);
        $I->sendPOST('api/region/' . $this->region['id'] . '/join');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function canJoinRegionTwice(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/' . $this->region['id'] . '/join');
        $I->sendPOST('api/region/' . $this->region['id'] . '/join');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        // database entry is not interesting, already tested that in other test
    }

    public function canNotLeaveRegionWithoutLogin(ApiTester $I): void
    {
        $I->sendPOST('api/region/' . $this->region['id'] . '/leave');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        $I->seeResponseIsJson();
        // cannot test whether leaving did not change database since
        // there is no user to look at
    }

    public function canLeaveRegionWithoutJoiningFirst(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/' . $this->region['id'] . '/leave');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function canLeaveRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/' . $this->region['id'] . '/join');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->login($this->user['email']);
        // second login necessary since the list of regions of the current
        // user are saved in the session and not updated there by the
        // join request. So without relogin the leave would fail.
        $I->sendPOST('api/region/' . $this->region['id'] . '/leave');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function canNotLeaveDifferentRegionThanJoined(ApiTester $I): void
    {
        $region2 = $I->createRegion();

        $I->login($this->user['email']);
        $I->addRegionMember($this->region['id'], $this->user['id'], true);
        $I->sendPOST('api/region/' . $region2['id'] . '/leave');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
        ]);
    }

    public function canNotLeaveNonExistingRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/999999999/leave');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
    }

    public function canNotLeaveRootRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPOST('api/region/' . RegionIDs::ROOT . '/leave');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseIsJson();
    }

    public function canNotLeaveRegionIfActiveStoreManager(ApiTester $I): void
    {
        $store = $I->createStore($this->region['id']);
        $coordinator = $I->createStoreCoordinator();
        $I->addRegionMember($this->region['id'], $coordinator['id'], true);
        $I->addStoreTeam($store['id'], $coordinator['id'], true, false, true);

        $I->login($coordinator['email']);
        $I->sendPOST('api/region/' . $this->region['id'] . '/leave');
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
    }

    public function canOnlyListRegionMembersAsMember(ApiTester $I): void
    {
        // test before being a member
        $I->login($this->user['email']);
        $I->sendGET('api/region/' . $this->region['id'] . '/members');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // test when being a member
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $I->login($this->user['email']); // relogin needed to initialise the session
        $I->sendGET('api/region/' . $this->region['id'] . '/members');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['id' => $this->user['id']]);
    }

    public function canListRegionMembersAsOrga(ApiTester $I): void
    {
        $I->login($this->userOrga['email']);
        $I->sendGET('api/region/' . $this->region['id'] . '/members');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function listMemberAsUser(ApiTester $I): void
    {
        $I->addRegionMember($this->region['id'], $this->user['id'], true);
        $I->login($this->user['email']);
        $I->sendGET('api/region/' . $this->region['id'] . '/members');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // the following fields should only be visible to admins
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].lastActivity');
        $I->assertNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].role');
        $I->assertNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].isVerified');
        $I->assertNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].isHomeRegion');
        $I->assertNull($responseItem[0]);
    }

    public function canSeeMemberDetailsAsAmbassador(ApiTester $I)
    {
        $I->login($this->userAmbassador['email']);
        $I->sendGET('api/region/' . $this->region['id'] . '/members');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // the following fields should only be visible to admins
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].lastActivity');
        $I->assertNotNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].role');
        $I->assertNotNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].isVerified');
        $I->assertNotNull($responseItem[0]);
        $responseItem = $I->grabDataFromResponseByJsonPath('$[*].isHomeRegion');
        $I->assertNotNull($responseItem[0]);
    }

    public function listOwnRegions(ApiTester $I): void
    {
        // Create region hierarchies
        $city1 = $I->createRegion('Ladenburg', ['type' => UnitType::CITY]);
        $city2 = $I->createRegion('Mannheim', ['type' => UnitType::BIG_CITY]);

        // Allocate users to region and groups
        $I->addRegionMember($city1['id'], $this->user['id']);
        $I->addRegionMember($city2['id'], $this->user['id']);
        $I->addRegionAdmin($city1['id'], $this->user['id']);

        $I->login($this->user['email']);
        $I->sendGET('api/user/current/regions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].id');
        $I->assertEquals(2, count($responseItems));
        $I->assertEquals($city1['id'], $responseItems[0]);
        $I->assertEquals($city2['id'], $responseItems[1]);

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].name');
        $I->assertEquals(2, count($responseItems));
        $I->assertEquals($city1['name'], $responseItems[0]);
        $I->assertEquals($city2['name'], $responseItems[1]);

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].classification');
        $I->assertEquals(2, count($responseItems));
        $I->assertEquals($city1['type'], $responseItems[0]);
        $I->assertEquals($city2['type'], $responseItems[1]);

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].isResponsible');
        $I->assertEquals(2, count($responseItems));
        $I->assertTrue($responseItems[0]);
        $I->assertFalse($responseItems[1]);
    }
}
