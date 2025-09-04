<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode;
use Tests\Support\ApiTester;

class GroupApiCest
{
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->region = $I->createRegion();
    }

    public function deleteGroupFailsForAmbassador(ApiTester $I): void
    {
        $ambassador = $I->createAmbassador();
        $I->login($ambassador['email']);
        $I->sendDELETE("api/groups/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }

    public function deleteGroupWorksForOrga(ApiTester $I): void
    {
        $orga = $I->createOrga();
        $I->login($orga['email']);
        $I->sendDELETE("api/groups/{$this->region['id']}");
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->dontSeeInDatabase('fs_bezirk', ['id' => $this->region['id']]);
    }

    public function listOwnGroups(ApiTester $I): void
    {
        $user = $I->createFoodsaver();

        // Group
        $group = $I->createWorkingGroup('Unit test AG');
        $I->addRegionMember($group['id'], $user['id']);
        $I->addRegionAdmin($group['id'], $user['id']);

        $group2 = $I->createWorkingGroup('Teaching AG');
        $I->addRegionMember($group2['id'], $user['id']);

        $I->login($user['email']);
        $I->sendGET('api/user/current/groups');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].id');
        $I->assertEquals(2, count($responseItems));
        $I->assertEquals($group['id'], $responseItems[0]);
        $I->assertEquals($group2['id'], $responseItems[1]);

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].name');
        $I->assertEquals(2, count($responseItems));
        $I->assertEquals($group['name'], $responseItems[0]);
        $I->assertEquals($group2['name'], $responseItems[1]);

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].isResponsible');
        $I->assertEquals(2, count($responseItems));
        $I->assertTrue($responseItems[0]);
        $I->assertFalse($responseItems[1]);
    }
}
