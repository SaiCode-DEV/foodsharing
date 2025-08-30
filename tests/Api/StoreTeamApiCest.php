<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus as STATUS;
use Tests\Support\ApiTester;

class StoreTeamApiCest
{
    private $store;
    private $user;
    private $manager;
    private array $manager2;
    private $region;

    private const string API_STORES = 'api/stores/';

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $I->createRegion(fillMailbox: false);
        $this->store = $I->createStore($this->region['id']);
        $this->user = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->manager = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $this->manager2 = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addStoreTeam($this->store['id'], $this->manager['id'], true);
    }

    public function cannotManageStoreTeamUnlessResponsible(ApiTester $I): void
    {
        $I->sendPOST(self::API_STORES . $this->store['id'] . '/invitations/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        $I->login($this->manager2['email']);
        $I->sendPOST(self::API_STORES . $this->store['id'] . '/invitations/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        $I->login($this->user['email']);
        $I->sendPOST(self::API_STORES . $this->store['id'] . '/invitations/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
    }

    public function canInviteTeamMember(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->sendPOST(self::API_STORES . $this->store['id'] . '/invitations/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::INVITED,
        ]);
    }

    public function canWithdrawIvitation(ApiTester $I): void
    {
        $I->haveInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::INVITED,
        ]);
        $I->login($this->manager['email']);
        $I->sendDelete(self::API_STORES . $this->store['id'] . '/invitations/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->dontSeeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
        ]);
    }

    public function canAcceptInvitation(ApiTester $I): void
    {
        $I->haveInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::INVITED,
        ]);
        $I->login($this->user['email']);
        $I->sendPatch(self::API_STORES . $this->store['id'] . '/invitations');
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function canDeclineInvitation(ApiTester $I): void
    {
        $I->haveInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::INVITED,
        ]);
        $I->login($this->user['email']);
        $I->sendDelete(self::API_STORES . $this->store['id'] . '/invitations');
        $I->seeResponseCodeIs(Http::OK);

        $I->dontSeeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
        ]);
    }

    /**
     * Test removing regular team members and standby team members.
     *
     * @example { "isStandby": false }
     * @example { "isStandby": true }
     */
    public function canRemoveTeamMember(ApiTester $I, Example $example): void
    {
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, $example['isStandby']);

        $I->login($this->manager['email']);
        $I->sendDELETE(self::API_STORES . $this->store['id'] . '/members/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->dontSeeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
        ]);
    }

    /**
     * @example { "isStandby": false }
     * @example { "isStandby": true }
     */
    public function cannotRemoveManager(ApiTester $I, Example $example): void
    {
        $I->addStoreTeam($this->store['id'], $this->manager2['id'], true, $example['isStandby']);

        $I->login($this->manager['email']);
        $I->sendDELETE(self::API_STORES . $this->store['id'] . '/members/' . $this->manager2['id']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->manager2['id'],
            'verantwortlich' => 1,
        ]);
    }

    /**
     * @example { "isStandby": false }
     * @example { "isStandby": true }
     */
    public function canPromoteToManager(ApiTester $I, Example $example): void
    {
        $I->addStoreTeam($this->store['id'], $this->manager2['id'], false, $example['isStandby']);

        $I->login($this->manager['email']);
        $I->sendPATCH(self::API_STORES . $this->store['id'] . '/managers/' . $this->manager2['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->manager2['id'],
            'verantwortlich' => 1,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function cannotPromoteToManager(ApiTester $I): void
    {
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, false);

        $I->login($this->manager['email']);
        $I->sendPATCH(self::API_STORES . $this->store['id'] . '/managers/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function canDemoteManager(ApiTester $I): void
    {
        $I->addStoreTeam($this->store['id'], $this->manager2['id'], true);

        $I->login($this->manager['email']);
        $I->sendDELETE(self::API_STORES . $this->store['id'] . '/managers/' . $this->manager2['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->manager2['id'],
            'verantwortlich' => 0,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function cannotDemoteLastManager(ApiTester $I): void
    {
        $I->login($this->manager['email']);
        $I->sendDELETE(self::API_STORES . $this->store['id'] . '/managers/' . $this->manager['id']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->manager['id'],
            'verantwortlich' => 1,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function canMoveTeamMemberToStandby(ApiTester $I): void
    {
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, false);

        $I->login($this->manager['email']);
        $I->sendPATCH(self::API_STORES . $this->store['id'] . '/members/' . $this->user['id'] . '/standby');
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::JUMPER,
        ]);
    }

    public function canMoveStandbyMemberToTeam(ApiTester $I): void
    {
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, true);

        $I->login($this->manager['email']);
        $I->sendDELETE(self::API_STORES . $this->store['id'] . '/members/' . $this->user['id'] . '/standby');
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => STATUS::MEMBER,
        ]);
    }

    public function canOnlySeeStoreMembersAsMember(ApiTester $I): void
    {
        // Not logged in should return 401
        $I->sendGET(self::API_STORES . $this->store['id'] . '/member');
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        // Logged in but not a member of the store should return 403
        $I->login($this->user['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . '/member');
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        // Logged in and member of the store should return a valid response
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, true);
        $I->sendGET(self::API_STORES . $this->store['id'] . '/member');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Make sure that the response contains all the team members as listed in the database
        $response = $I->grabDataFromResponseByJsonPath('$[*].id');
        $actualMembers = $I->grabColumnFromDatabase('fs_betrieb_team', 'foodsaver_id', [
            'betrieb_id' => $this->store['id'],
        ]);
        $I->assertEquals(array_diff($response, $actualMembers), []);
    }
}
