<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Store\StoreTransactions;
use Tests\Support\ApiTester;

/**
 * @group api-group-2
 */
class PickupManagementApiCest
{
    private $user1;
    private $coordinator;
    private $store;
    private $region;

    public function _before(ApiTester $I): void
    {
        $this->user1 = $I->createFoodsaver();
        $this->coordinator = $I->createStoreCoordinator();
        $this->region = $I->createRegion();
        $this->store = $I->createStore($this->region['id']);
        $I->addStoreTeam($this->store['id'], $this->coordinator['id'], true);
        $I->addStoreTeam($this->store['id'], $this->user1['id'], false);
    }

    public function createManualPickUpee(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function createManualPickUpInvalidStore(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/badStoreId/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function createManualPickUpUnknownStore(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . ($this->store['id'] + 1) . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function createManualPickUpInvalidDateStore(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::parse('2001-12-12');
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function createManualPickUpExpiredDateStore(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->sub('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function createManualPickUpOutOfRangeSlotCountStore(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => StoreTransactions::MAX_SLOTS_PER_PICKUP + 1]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function createManualPickUpNoSlotShouldBeAllowedForStoreVacationAndReplaceRegularPickUp(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 0]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function createManualPickUpInvalidPermission(ApiTester $I): void
    {
        $I->login($this->user1['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function createManualPickUpAnonym(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
    }

    public function modifyManualPickUp(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 5]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function modifyManualPickUpToNoSlotAsReserved(ApiTester $I): void
    {
        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 0]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function modifyRegularPickUpAlreadyOccupiedSlots(ApiTester $I): void
    {
        $user2 = $I->createFoodsaver();
        $I->addStoreTeam($this->store['id'], $user2['id'], false);

        $I->login($this->coordinator['email']);
        $pickupBaseDate = Carbon::now()->sub('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store['id'], $this->user1['id'], ['date' => $pickupBaseDate->toISOString()]);
        $I->addPicker($this->store['id'], $user2['id'], ['date' => $pickupBaseDate->toISOString()]);
        $I->sendPut('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toISOString(), ['totalSlots' => 1]);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }
}
