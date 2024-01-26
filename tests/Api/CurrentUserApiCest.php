<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode as Http;
use Faker\Factory;
use Foodsharing\Modules\Core\DBConstants\Store\CooperationStatus;
use Foodsharing\Modules\Core\DBConstants\StoreTeam\MembershipStatus;
use Tests\Support\ApiTester;

/**
 * Tests for the endpoints for the current user api.
 */
class CurrentUserApiCest
{
    private $user;
    private $storeAsManager;
    private $storeAsMember;
    private $storeAsJumper;
    private $storeWithJoinRequest;

    private const EMAIL = 'email';
    private const API_USER = 'api/user';

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->userOrga = $I->createOrga();

        $this->region_1 = $I->createRegion();

        $this->faker = Factory::create('de_DE');
    }

    private function prepareStoreMembership(ApiTester $I): void
    {
        $this->storeAsManager = $I->createStore($this->region_1['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->storeAsManager['id'], $this->user['id'], true);

        $this->storeAsMember = $I->createStore($this->region_1['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->storeAsMember['id'], $this->user['id'], false);

        $this->storeAsJumper = $I->createStore($this->region_1['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->storeAsJumper['id'], $this->user['id'], false, true, true);

        $this->storeWithJoinRequest = $I->createStore($this->region_1['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->storeWithJoinRequest['id'], $this->user['id'], false, false, false);

        $this->region_2 = $I->createRegion();
        $this->r2_storeAsMember = $I->createStore($this->region_2['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->r2_storeAsMember['id'], $this->user['id'], false);

        $this->user_2 = $I->createFoodsaver();
        $this->region_2 = $I->createRegion();
        $this->r2_u2_storeAsMember = $I->createStore($this->region_2['id'], null, null, ['betrieb_status_id' => CooperationStatus::COOPERATION_ESTABLISHED->value]);
        $I->addStoreTeam($this->r2_u2_storeAsMember['id'], $this->user_2['id'], false);
    }

    public function getStoreNoTeamMembership(ApiTester $I): void
    {
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/current/stores');
        $I->seeResponseCodeIs(Http::NO_CONTENT);
    }

    private function assertArrayEquals(ApiTester $I, array $expect, array $actual, string $message = ''): void
    {
        $I->assertCount(count($expect), $actual, "{$message}: Count of array element is wrong");
        for ($i = 0, $iMax = count($expect); $i < $iMax; ++$i) {
            $I->assertEquals($expect[$i], $actual[$i], "{$message}:  Element {$i} not matchs.");
        }
    }

    private function sortStoresLikeDB(array $items): array
    {
        usort($items, function ($a, $b) {
            if ($a['isManaging'] == $b['isManaging']) {
                if ($a['membershipStatus'] == $b['membershipStatus']) {
                    return strcmp((string)$a['name'], (string)$b['name']);
                }
                if ($a['membershipStatus'] < $b['membershipStatus']) {
                    return -1;
                }

                return 1;
            }
            if ($a['isManaging'] > $b['isManaging']) {
                return -1;
            }

            return 1;
        });

        return $items;
    }

    private function getValuesByKey(array $items, string $key): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = $item[$key];
        }

        return $out;
    }

    public function getStoreStatusWithDifferentTeamMemberships(ApiTester $I): void
    {
        $this->prepareStoreMembership($I);
        // Prepare expectation
        $date = Carbon::now()->add('2 days')->hours(16)->minutes(30)->seconds(0)->microseconds(0);
        $dow = $date->weekday();
        $I->addRecurringPickup($this->storeAsManager['id'], ['time' => '16:30:00', 'dow' => $dow, 'fetcher' => 1]);
        $this->storeAsManager['nextPickup'] = 2;
        $this->storeAsManager['membershipStatus'] = MembershipStatus::MEMBER;
        $this->storeAsManager['isManaging'] = true;

        $this->storeWithJoinRequest['membershipStatus'] = MembershipStatus::APPLIED_FOR_TEAM;
        $this->storeWithJoinRequest['isManaging'] = false;
        $this->storeWithJoinRequest['nextPickup'] = null;

        $this->storeAsMember['membershipStatus'] = MembershipStatus::MEMBER;
        $this->storeAsMember['isManaging'] = false;
        $this->storeAsMember['nextPickup'] = 0;
        $this->r2_storeAsMember['membershipStatus'] = MembershipStatus::MEMBER;
        $this->r2_storeAsMember['isManaging'] = false;
        $this->r2_storeAsMember['nextPickup'] = 0;
        $this->storeAsJumper['membershipStatus'] = MembershipStatus::JUMPER;
        $this->storeAsJumper['isManaging'] = false;
        $this->storeAsJumper['nextPickup'] = null;

        $db_items = [$this->storeAsManager, $this->storeWithJoinRequest, $this->storeAsMember,
        $this->r2_storeAsMember, $this->storeAsJumper];
        $db_items = $this->sortStoresLikeDB($db_items);

        // Run
        $I->login($this->user[self::EMAIL]);
        $I->sendGet(self::API_USER . '/current/stores');
        $I->seeResponseCodeIs(Http::OK);
        $I->seeResponseIsJson();

        // Check response content
        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].id');
        $I->assertEquals(5, count($responseItems));

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].name');
        $expected = $this->getValuesByKey($db_items, 'name');
        $this->assertArrayEquals($I, $expected, $responseItems, 'Store name');

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].id');
        $expected = $this->getValuesByKey($db_items, 'id');
        $this->assertArrayEquals($I, $expected, $responseItems, 'Store Id');

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].membershipStatus');
        $expected = $this->getValuesByKey($db_items, 'membershipStatus');
        $this->assertArrayEquals($I, $expected, $responseItems, 'Membership name');

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].isManaging');
        $expected = $this->getValuesByKey($db_items, 'isManaging');
        $this->assertArrayEquals($I, $expected, $responseItems, 'Manager status');

        $responseItems = $I->grabDataFromResponseByJsonPath('$[*].pickupStatus');
        $expected = $this->getValuesByKey($db_items, 'nextPickup');
        $this->assertArrayEquals($I, $expected, $responseItems, 'Pickup status');
    }
}
