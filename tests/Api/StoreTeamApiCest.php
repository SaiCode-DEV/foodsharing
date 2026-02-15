<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Example;
use Codeception\Util\HttpCode as Http;
use Foodsharing\Modules\Core\DBConstants\Region\WorkgroupFunction;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class StoreTeamApiCest
{
    private array $store;
    private array $user;
    private array $user2;
    private array $manager;
    private array $manager2;
    private array $region;
    private array $beko;
    private array $ambassador;
    private array $orga;

    private const string API_STORES = 'api/stores/';

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $I->createRegion(fillMailbox: false);
        $this->store = $I->createStore($this->region['id']);
        $this->user = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->user2 = $I->createFoodsaver(null, ['bezirk_id' => $this->region['id']]);
        $this->manager = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $this->manager2 = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addStoreTeam($this->store['id'], $this->user2['id']);
        $I->addStoreTeam($this->store['id'], $this->manager['id'], true);
        $this->ambassador = $I->createAmbassador(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionAdmin($this->region['id'], $this->ambassador['id']);
        $this->orga = $I->createOrga();
    }

    private function createBeKo(ApiTester $I): void
    {
        // Create BeKo member for region store coordination
        $ag_beko = $I->createWorkingGroup('Betriebskoordination', [
            'parent_id' => $this->region['id'],
            'email' => 'beko',
            'teaser' => 'Hier ist die AG Betriebskoordination für unseren Bezirk',
        ]);
        $I->haveInDatabase('fs_region_function', [
            'region_id' => $ag_beko['id'],
            'function_id' => WorkgroupFunction::STORES_COORDINATION,
            'target_id' => $this->region['id'],
        ]);
        $this->beko = $I->createStoreCoordinator(null, ['bezirk_id' => $this->region['id']]);
        $I->addRegionMember($ag_beko['id'], $this->beko['id']);
        $I->addRegionAdmin($ag_beko['id'], $this->beko['id']);
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
            'active' => MembershipStatus::INVITED,
        ]);
    }

    public function canWithdrawIvitation(ApiTester $I): void
    {
        $I->haveInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => MembershipStatus::INVITED,
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
            'active' => MembershipStatus::INVITED,
        ]);
        $I->login($this->user['email']);
        $I->sendPatch(self::API_STORES . $this->store['id'] . '/invitations');
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => MembershipStatus::MEMBER,
        ]);
    }

    public function canDeclineInvitation(ApiTester $I): void
    {
        $I->haveInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => MembershipStatus::INVITED,
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
        $I->sendPost(self::API_STORES . $this->store['id'] . '/managers/' . $this->manager2['id']);
        $I->seeResponseCodeIs(Http::OK);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->manager2['id'],
            'verantwortlich' => 1,
            'active' => MembershipStatus::MEMBER,
        ]);
    }

    public function cannotPromoteToManager(ApiTester $I): void
    {
        $I->addStoreTeam($this->store['id'], $this->user['id'], false, false);

        $I->login($this->manager['email']);
        $I->sendPost(self::API_STORES . $this->store['id'] . '/managers/' . $this->user['id']);
        $I->seeResponseCodeIs(Http::UNPROCESSABLE_ENTITY);

        $I->seeInDatabase('fs_betrieb_team', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->user['id'],
            'verantwortlich' => 0,
            'active' => MembershipStatus::MEMBER,
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
            'active' => MembershipStatus::MEMBER,
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
            'active' => MembershipStatus::MEMBER,
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
            'active' => MembershipStatus::JUMPER,
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
            'active' => MembershipStatus::MEMBER,
        ]);
    }

    /**
     * Ensure that only team members can see store details like members and pickups.
     * This aligns with the permissions of /stores/{storeId}/member.
     *
     * @example [ "/members", "Not allowed to see store members." ]
     * @example [ "/permissions", "Not allowed to see store permissions." ]
     * @example [ "/pickups", "You are not allowed to see pickups in this store." ]
     */
    public function canOnlySeeStoreInternalsIfAllowed(ApiTester $I, Example $E): void
    {
        // Not logged in should return 401
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::UNAUTHORIZED);

        // Logged in but not a member of the store should return 403
        $I->login($this->user['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'code' => Http::FORBIDDEN,
            'message' => $E[1],
        ]);

        // Logged in and member of the store should return a valid response
        $I->login($this->user2['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Logged in as store coordinator without being a team member should return 403
        $I->login($this->manager2['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);

        // Logged in as store coordinator team should return a valid response
        $I->login($this->manager['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Logged in as Orga user should return a valid response (even without
        // being member of the region)
        $I->login($this->orga['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Logged in as ambassador should return a valid response
        $I->login($this->ambassador['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Create BeKo user
        $this->createBeKo($I);

        // Logged in as ambassador not being member of the store when BeKo
        // exists should return 403
        $I->login($this->ambassador['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'code' => Http::FORBIDDEN,
            'message' => $E[1],
        ]);

        // Logged in as BeKo should return a valid response
        $I->login($this->beko['email']);
        $I->sendGET(self::API_STORES . $this->store['id'] . $E[0]);
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        if ($E[0] === '/member') {
            // Make sure that the response contains all the team members as listed in the database
            $response = $I->grabDataFromResponseByJsonPath('$[*].id');
            $actualMembers = $I->grabColumnFromDatabase('fs_betrieb_team', 'foodsaver_id', [
                'betrieb_id' => $this->store['id'],
            ]);

            // Esnure both arrays are non-empty
            $I->assertNotEmpty($response);
            $I->assertNotEmpty($actualMembers);
            // Ensure both arrays have the same number of elements
            $I->assertCount(count($actualMembers), $response);

            // Ensure both arrays are equal
            $I->assertEqualsCanonicalizing($response, $actualMembers);
        } elseif ($E[0] === '/permissions') {
            // Ensure that the response contains the permissions for the store
            // team member (the last response comes from the BeKo user)
            $I->seeResponseContainsJson([
                'isCoordinator' => true,
                'isAmbassador' => false,
                'isOrgaUser' => false,
                'isJumper' => false,
                'isManager' => false,
                'maySeePickup' => true,
                'mayEditStore' => true,
                'mayLeaveStoreTeam' => true,
                'maySeePickupHistory' => true,
                'maySeeStoreLog' => true,
                'maySeePickups' => true,
            ]);
        } elseif ($E[0] === '/pickups') {
            // Ensure that the response contains an empty array (no pickups created yet)
            $I->seeResponseIsJson();
        }
    }
}
