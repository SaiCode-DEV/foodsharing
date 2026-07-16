<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class RegionApiCest
{
    private $user;
    private $userAmbassador;
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
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
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
        ]);
    }

    public function joiningAnAlreadyJoinedRegionSetsTheMissingHomeRegion(ApiTester $I): void
    {
        // "Wechsler" state (#2771): still a member of the region, but no home region
        $wechsler = $I->createFoodsaver(null, ['bezirk_id' => 0]);
        $I->addRegionMember($this->region['id'], $wechsler['id']);

        $I->login($wechsler['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $wechsler['id'],
            'bezirk_id' => $this->region['id'],
        ]);
    }

    public function joiningANewRegionSetsTheMissingHomeRegion(ApiTester $I): void
    {
        $user = $I->createFoodsaver(null, ['bezirk_id' => 0]);

        $I->login($user['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $user['id'],
            'bezirk_id' => $this->region['id'],
        ]);
    }

    public function joiningDoesNotOverwriteAnExistingHomeRegion(ApiTester $I): void
    {
        $homeRegion = $I->createRegion();
        $user = $I->createFoodsaver(null, ['bezirk_id' => $homeRegion['id']]);
        $I->addRegionMember($this->region['id'], $user['id']);

        $I->login($user['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $user['id'],
            'bezirk_id' => $homeRegion['id'],
        ]);
    }

    public function joinNotExistingRegionIs404(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPut('api/regions/999999999/users/current');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => 999_999_999
            ]);
    }

    public function canNotJoinRegionAsFoodsharer(ApiTester $I): void
    {
        $foodsharer = $I->createFoodsharer();
        $I->login($foodsharer['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function canJoinRegionTwice(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        // database entry is not interesting, already tested that in other test
    }

    public function canNotLeaveRegionWithoutLogin(ApiTester $I): void
    {
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
        // cannot test whether leaving did not change database since
        // there is no user to look at
    }

    public function canLeaveRegionWithoutJoiningFirst(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
            ]);
    }

    public function canLeaveRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendPut('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->login($this->user['email']);
        // second login necessary since the list of regions of the current
        // user are saved in the session and not updated there by the
        // join request. So without relogin the leave would fail.
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
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
        $I->sendDelete('api/regions/' . $region2['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeInDatabase('fs_foodsaver_has_bezirk', [
            'foodsaver_id' => $this->user['id'],
            'bezirk_id' => $this->region['id']
        ]);
    }

    public function canNotLeaveNonExistingRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDelete('api/regions/999999999/users/current');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canNotLeaveRootRegion(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $I->sendDelete('api/regions/' . RegionIDs::ROOT . '/users/current');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function canNotLeaveRegionIfStoreManager(ApiTester $I): void
    {
        $store = $I->createStore($this->region['id']);
        $coordinator = $I->createStoreCoordinator();
        $I->addRegionMember($this->region['id'], $coordinator['id'], true);
        $I->addStoreTeam($store['id'], $coordinator['id'], true, false, true);

        $I->login($coordinator['email']);
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
    }

    public function canNotLeaveRegionIfStoreMember(ApiTester $I): void
    {
        $store = $I->createStore($this->region['id']);
        $I->addStoreTeam($store['id'], $this->user['id'], false, false, true);

        $I->login($this->user['email']);
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
    }

    public function canNotLeaveRegionIfStoreJumper(ApiTester $I): void
    {
        $store = $I->createStore($this->region['id']);
        $I->addStoreTeam($store['id'], $this->user['id'], false, true, true);

        $I->login($this->user['email']);
        $I->sendDelete('api/regions/' . $this->region['id'] . '/users/current');
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
    }

    public function canOnlyListRegionMembersAsMember(ApiTester $I): void
    {
        // test before being a member
        $I->login($this->user['email']);
        $I->sendGET('api/regions/' . $this->region['id'] . '/users');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // test when being a member
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $I->login($this->user['email']); // relogin needed to initialise the session
        $I->sendGET('api/regions/' . $this->region['id'] . '/users');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['id' => $this->user['id']]);
    }

    public function canListRegionMembersAsOrga(ApiTester $I): void
    {
        $userOrga = $I->createOrga();

        $I->login($userOrga['email']);
        $I->sendGET('api/regions/' . $this->region['id'] . '/users');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function listMemberAsUser(ApiTester $I): void
    {
        $I->addRegionMember($this->region['id'], $this->user['id'], true);
        $I->login($this->user['email']);
        $I->sendGET('api/regions/' . $this->region['id'] . '/users');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        // the following fields should only be visible to admins
        $I->dontSeeResponseJsonMatchesJsonPath('$[*].lastActivity');
        $I->dontSeeResponseJsonMatchesJsonPath('$[*].role');
        $I->dontSeeResponseJsonMatchesJsonPath('$[*].isVerified');
        $I->dontSeeResponseJsonMatchesJsonPath('$[*].isHomeRegion');
    }

    public function canSeeMemberDetailsAsAmbassador(ApiTester $I)
    {
        $I->login($this->userAmbassador['email']);
        $I->sendGET('api/regions/' . $this->region['id'] . '/users');
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
}
