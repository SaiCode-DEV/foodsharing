<?php

declare(strict_types=1);

namespace Tests\Api;

use Codeception\Util\HttpCode as Http;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class StoreRegionMoveApiCest
{
    private array $sourceRegion;
    private array $targetRegion;
    private array $store;
    private array $members = [];

    public function _before(ApiTester $I): void
    {
        $this->sourceRegion = $I->createRegion(fillMailbox: false);
        $this->targetRegion = $I->createRegion(fillMailbox: false);
        $this->store = $I->createStore($this->sourceRegion['id']);
        $this->members = [];
    }

    public function _testMoveStore(ApiTester $I, $shouldFail): void
    {
        // Create five foodsavers and add them to the store team. If we want the
        // tests to fail, one of them is not added to the target region, so the
        // move should be prevented and the response should contain the name of
        // the missing member.
        for ($i = 0; $i < 5; ++$i) {
            $member = $I->createFoodsaver(null, ['bezirk_id' => $this->sourceRegion['id']]);
            $I->addStoreTeam($this->store['id'], $member['id']);
            if (!$shouldFail || $i < 4) {
                $I->addRegionMember($this->targetRegion['id'], $member['id']);
            }
            $this->members[] = $member;
        }

        $orga = $I->createOrga();
        $I->login($orga['email']);

        $I->sendPatch('api/stores/' . $this->store['id'] . '/details', [
            'regionId' => $this->targetRegion['id'],
        ]);
    }

    public function cannotMoveStoreWhenTeamMembersNotInTargetRegion(ApiTester $I): void
    {
        $this->_testMoveStore($I, shouldFail: true);

        $I->seeResponseCodeIs(Http::BAD_REQUEST);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['message' => 'Es gibt Teammitglieder, die nicht Teil der Zielregion sind: ' . $this->members[4]['name'] . ' (ID: ' . $this->members[4]['id'] . ')', 'code' => 400]);
    }

    public function canMoveStoreWhenAllTeamMembersAreInTargetRegion(ApiTester $I): void
    {
        $this->_testMoveStore($I, shouldFail: false);

        $I->seeResponseCodeIs(Http::OK);
        $I->seeInDatabase('fs_betrieb', [
            'id' => $this->store['id'],
            'bezirk_id' => $this->targetRegion['id'],
        ]);
    }
}
