<?php

declare(strict_types=1);

namespace Tests\Api;

use Carbon\Carbon;
use Codeception\Util\HttpCode;
use Foodsharing\Modules\Event\InvitationStatus;
use Tests\Support\ApiTester;

class PickupApiCest
{
    private $user;
    private $storeCoordinator;
    private $store;
    private $store2;
    private $store3;
    private $store4;
    private $region;
    private $waiter;

    public function _before(ApiTester $I): void
    {
        $this->user = $I->createFoodsaver();
        $this->storeCoordinator = $I->createStoreCoordinator();
        $this->region = $I->createRegion(fillMailbox: false);
        $I->addRegionMember($this->region['id'], $this->user['id']);
        $this->store = $I->createStore($this->region['id']);
        $I->addStoreTeam($this->store['id'], $this->user['id']);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);
        $this->waiter = $I->createFoodsaver();
        $I->addStoreTeam($this->store['id'], $this->waiter['id'], false, true);
        $this->store2 = $I->createStore($this->region['id'], null, null, ['use_region_pickup_rule' => 1]);
        $I->addStoreTeam($this->store2['id'], $this->user['id']);
        $this->store3 = $I->createStore($this->region['id'], null, null, ['use_region_pickup_rule' => 1]);
        $I->addStoreTeam($this->store3['id'], $this->user['id']);
        $this->store4 = $I->createStore($this->region['id'], null, null, ['use_region_pickup_rule' => 1]);
        $I->addStoreTeam($this->store4['id'], $this->user['id']);
    }

    public function acceptsDifferentIsoFormats(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $id = $this->user['id'];
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(13)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s') . '+0000/' . $id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $pickupBaseDate->minutes(50);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->copy()->setTimezone('+01:00')->format('Y-m-d\TH:i:s') . '.000+01:00/' . $id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $pickupBaseDate->minutes(55);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->copy()->setTimezone('-01:00')->format('Y-m-d\TH:i:s') . '-01:00/' . $id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $pickupBaseDate->minutes(35);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s') . 'Z/' . $id);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
    }

    public function signupAsWaiterDoesNotWork(ApiTester $I): void
    {
        $I->login($this->waiter['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(50)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->waiter['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function signupForDifferentUserShouldBeRejected(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(50)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->waiter['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function signupForNotAvailableSlots(ApiTester $I): void
    {
        $I->login($this->user['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(50)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 1]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->waiter['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function signupReturnsPickupConfirmationState(ApiTester $I): void
    {
        $I->login($this->storeCoordinator['email']);
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 1]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->storeCoordinator['id']);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function pickupDescriptionVisible(ApiTester $I)
    {
        $I->login($this->user['email']);

        //Create a pickup
        $pickupBaseDate = Carbon::now()->add('1 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 1, 'description' => 'some description']);

        $I->sendGet('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'description' => 'some description'
        ]);
    }

    public function pickupDescriptionEditable(ApiTester $I)
    {
        $I->login($this->storeCoordinator['email']);

        //Create a pickup
        $pickupBaseDate = Carbon::now()->add('1 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 1]);

        $I->sendPatch('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String(),
            ['description' => 'random description', 'totalSlots' => 3]
        );
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'created' => false
        ]);

        $I->sendGet('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'description' => 'random description'
        ]);
    }

    public function createOnetimePickupWithDescription(ApiTester $I)
    {
        $I->login($this->storeCoordinator['email']);

        //Create a pickup
        $pickupBaseDate = Carbon::now()->add('1 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);

        $I->sendPatch('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String(),
            ['description' => 'another random description', 'totalSlots' => 3]
        );
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'created' => true
        ]);

        $I->sendGet('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'description' => 'another random description'
        ]);
    }

    public function createAndEnterRegularPickupWithDescription(ApiTester $I)
    {
        $coordinator = $I->createStoreCoordinator();
        $I->addStoreTeam($this->store['id'], $coordinator['id'], true, false, true);
        $I->login($coordinator['email']);

        //Create a pickup
        $pickupBaseDate = Carbon::now()->addDay()->add('1 weeks');
        $pickupBaseDate->hours(11)->minutes(30)->seconds(0);

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPut('api/stores/' . $this->store['id'] . '/regularPickup',
            [[
                'description' => 'regular slot description',
                'maxCountOfSlots' => 1,
                'startTimeOfPickup' => '11:30:00',
                'weekday' => $pickupBaseDate->dayOfWeek,
            ]]
        );
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'description' => 'regular slot description'
        ]);

        // Enter into that regular slot
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $coordinator['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGet('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);

        // Make sure the description of the regular pickup slot is still there (now as a onetime pickup)
        $I->canSeeResponseContainsJson([
            'description' => 'regular slot description',
            'occupiedSlots' => [
                ['isConfirmed' => true]
            ]
        ]);
    }

    public function signupAsCoordinarIsPreconfirmed(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(16)->minutes(45)->seconds(0);
        $coordinator = $I->createStoreCoordinator();
        $I->addStoreTeam($this->store['id'], $coordinator['id'], true, false, true);
        $I->login($coordinator['email']);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendPOST('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $coordinator['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'isConfirmed' => true
        ]);
    }

    public function AsWaiterICannotSeePickups(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(16)->minutes(55)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->login($this->waiter['email']);
        $I->sendGET('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function testSinglePickupInListExistsAndIsValid(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(16)->minutes(55)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->login($this->user['email']);
        $I->sendGET('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'pickups' => [[
            'date' => $pickupBaseDate->toIso8601String(),
            'totalSlots' => 2,
            'occupiedSlots' => [],
            'isAvailable' => true
            ]]
        ]);
    }

    public function testListPickupWithHistoryShowFutureAndHistory(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->addMinute()->second(0);

        // Pickup 5 hours ago - regular replaced by manual planed
        $regularPickup5HoursBeforeDate = $pickupBaseDate->copy()->subHours(5);
        $I->addRecurringPickup($this->store['id'], [
            'dow' => $regularPickup5HoursBeforeDate->dayOfWeek,
            'time' => sprintf('%02d:%s:00', $regularPickup5HoursBeforeDate->hour, $pickupBaseDate->minute),
            'fetcher' => 4
        ]);
        $I->addPickup($this->store['id'], ['time' => $regularPickup5HoursBeforeDate, 'fetchercount' => 4]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $regularPickup5HoursBeforeDate]);

        // Pickup 3 hours ago - manual planed
        $manualPickup3HoursBeforeDate = $pickupBaseDate->copy()->subHours(3);
        $I->addPickup($this->store['id'], ['time' => $manualPickup3HoursBeforeDate, 'fetchercount' => 1]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $manualPickup3HoursBeforeDate]);

        // Pickup 1 hour ago - regular planed (not replaced by manual planed)
        $regularPickup1HoursBeforeDate = $pickupBaseDate->copy()->subHours(1);
        $I->addRecurringPickup($this->store['id'], [
            'dow' => $regularPickup1HoursBeforeDate->dayOfWeek,
            'time' => sprintf('%02d:%s:00', $regularPickup1HoursBeforeDate->hour, $pickupBaseDate->minute),
            'fetcher' => 3
        ]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $regularPickup1HoursBeforeDate]);

        // Pickup in future
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);

        // Test
        $I->login($this->user['email']);
        $I->sendGET('api/stores/' . $this->store['id'] . '/pickups');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'pickups' => [
                [
                    'date' => $pickupBaseDate->toIso8601String(),
                    'totalSlots' => 2,
                    'occupiedSlots' => [],
                    'isAvailable' => true
                ], [
                    'date' => $manualPickup3HoursBeforeDate->toIso8601String(),
                    'totalSlots' => 1,
                    'occupiedSlots' => [['isConfirmed' => true, 'profile' => ['id' => $this->user['id']]]],
                    'isAvailable' => false
                ], [
                    'date' => $regularPickup1HoursBeforeDate->toIso8601String(),
                    'totalSlots' => 3,
                    'occupiedSlots' => [['isConfirmed' => true, 'profile' => ['id' => $this->user['id']]]],
                    'isAvailable' => false
                ], [
                    'date' => $regularPickup5HoursBeforeDate->toIso8601String(),
                    'totalSlots' => 4,
                    'occupiedSlots' => [['isConfirmed' => true, 'profile' => ['id' => $this->user['id']]]],
                    'isAvailable' => false
                ]
            ]
        ]);
    }

    public function testSinglePickupHistoryInListExistsAndIsValid(ApiTester $I): void
    {
        $refDate = Carbon::now()->subYears(3)->subHours(8);
        $I->haveInDatabase('fs_abholer', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->storeCoordinator['id'],
            'date' => $refDate
        ]);

        $e = $I->grabFromDatabase('fs_abholer', 'date', [
            'betrieb_id' => $this->store['id'],
            'foodsaver_id' => $this->storeCoordinator['id']
        ]);

        $startDate = $refDate->copy()->subYears(2);
        $endDate = $refDate->copy()->addYears(2);

        $I->login($this->storeCoordinator['email']);
        $I->sendGET('api/stores/' . $this->store['id'] . '/history/' . $startDate->toIso8601String() . '/' . $endDate->toIso8601String());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'pickups' => [
                [
                    'occupiedSlots' => [
                        [
                            'profile' => [
                                'id' => $this->storeCoordinator['id']
                            ],
                            'date' => $refDate->toIso8601String(),
                            'date_ts' => $refDate->timestamp,
                            'confirmed' => 0
                        ]
                    ]
            ]]
        ]);
    }

    public function cannotSignOutOfPastPickup(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->sub('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $I->login($this->user['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendDELETE('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function canSignOutOfPickupWithMessage(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $I->login($this->storeCoordinator['email']);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendDELETE('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id'], ['sendKickMessage' => true, 'message' => 'Hallo']);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function checkDistrictRules(ApiTester $I): void
    {
        /*
              Create PickupRule

            7 days, max pickup 3 overall, max 2 per day, rule ignored 48 hours before pickup
        */
        $I->createDistrictPickupRule((int)$this->region['id'], '7', '3', '2', '48');
        $I->login($this->user['email']);

        // Test for maximum 3 pickups in 7 days over multiple stores.
        $pickupBaseDate = Carbon::now()->add('3 days');
        $pickupBaseDate->hours(10)->minutes(00)->seconds(0);
        $I->addPickup($this->store2['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store2['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->add('4 days');
        $pickupBaseDate->hours(11)->minutes(00)->seconds(0);
        $I->addPickup($this->store3['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store3['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->add('5 days');
        $pickupBaseDate->hours(11)->minutes(00)->seconds(0);
        $I->addPickup($this->store4['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);

        // This signup is ok because it is the third one
        $I->sendGET('api/stores/' . $this->store4['id'] . '/pickupRuleCheck/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'result' => true
        ]);

        // this signup breaks the rule as it is the fourth one
        $I->addPicker($this->store4['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->add('6 days');
        $pickupBaseDate->hours(11)->minutes(00)->seconds(0);
        $I->addPickup($this->store4['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendGET('api/stores/' . $this->store4['id'] . '/pickupRuleCheck/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'result' => false
        ]);

        // Test for third signups on the same day over multiple stores
        $pickupBaseDate = Carbon::now()->add('20 days');
        $pickupBaseDate->hours(10)->minutes(00)->seconds(0);
        $I->addPickup($this->store2['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store2['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->add('20 days');
        $pickupBaseDate->hours(11)->minutes(00)->seconds(0);
        $I->addPickup($this->store3['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store3['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->add('20 days');
        $pickupBaseDate->hours(12)->minutes(00)->seconds(0);
        $I->addPickup($this->store4['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendGET('api/stores/' . $this->store4['id'] . '/pickupRuleCheck/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'result' => false
        ]);

        // test for ignoring of the rule if signup date is closer then ignorerulehours
        $pickupBaseDate = Carbon::now()->addDay();
        $pickupBaseDate->hours(10)->minutes(00)->seconds(0);
        $I->addPickup($this->store2['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store2['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->addDay();
        $pickupBaseDate->hours(11)->minutes(00)->seconds(0);
        $I->addPickup($this->store4['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->addPicker($this->store4['id'], $this->user['id'], ['date' => $pickupBaseDate]);

        $pickupBaseDate = Carbon::now()->addDay();
        $pickupBaseDate->hours(12)->minutes(00)->seconds(0);
        $I->addPickup($this->store4['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);
        $I->sendGET('api/stores/' . $this->store4['id'] . '/pickupRuleCheck/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseContainsJson([
            'result' => true
        ]);
    }

    public function cannotJoinPickupExpiredPassport(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->add('2 days');
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);

        // Set expired passport
        $I->updateInDatabase('fs_foodsaver', [
            'last_pass' => Carbon::now()->subYears(5)->toDateTimeString()
        ], [
            'id' => $this->user['id']
        ]);

        $I->login($this->user['email']);
        $I->sendPost('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function cannotJoinPickupAfterPassportExpiry(ApiTester $I): void
    {
        $pickupBaseDate = Carbon::now()->addYears(4);
        $pickupBaseDate->hours(14)->minutes(45)->seconds(0);
        $I->addPickup($this->store['id'], ['time' => $pickupBaseDate, 'fetchercount' => 2]);

        // Set expired passport
        $I->updateInDatabase('fs_foodsaver', [
            'last_pass' => Carbon::now()->subYears(1)->toDateTimeString()
        ], [
            'id' => $this->user['id']
        ]);

        $I->login($this->user['email']);
        $I->sendPost('api/stores/' . $this->store['id'] . '/pickups/' . $pickupBaseDate->toIso8601String() . '/' . $this->user['id']);
        $I->seeResponseIsJson();
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    public function listSameDayAgenda(ApiTester $I)
    {
        // Add past pickup
        $past_pickupDate = Carbon::now()->subMinutes(10);
        $I->addPickup($this->store['id'], ['time' => $past_pickupDate, 'fetchercount' => 3]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $past_pickupDate]);

        // Add future pickup
        $pickupDate = $past_pickupDate->copy()->addMinutes(3);
        $I->addPickup($this->store['id'], ['time' => $pickupDate, 'fetchercount' => 1]);
        $I->addPicker($this->store['id'], $this->user['id'], ['date' => $pickupDate]);

        // Create a future event for the current user
        $eventDate = $pickupDate->copy()->addMinutes(1);
        $eventParams = [
            'name' => 'Test Event',
            'start' => $eventDate->format('Y-m-d H:i:s'),
            'end' => $eventDate->addHour()->format('Y-m-d H:i:s'),
        ];
        $event = $I->createEvents($this->region['id'], $this->user['id'], $eventParams);
        $I->addEventInvitation($event['id'], $this->user['id'], [
            'status' => InvitationStatus::ACCEPTED
        ]);

        // Create a past event for the current user
        $past_eventDate = $pickupDate->copy()->subDays(1);
        $past_eventParams = [
            'name' => 'Past Event',
            'start' => $past_eventDate->format('Y-m-d H:i:s'),
            'end' => $past_eventDate->addHour()->format('Y-m-d H:i:s'),
        ];
        $past_event = $I->createEvents($this->region['id'], $this->user['id'], $past_eventParams);
        $I->addEventInvitation($past_event['id'], $this->user['id'], [
            'status' => InvitationStatus::MAYBE
        ]);

        // Create a future event on another day != pickup day
        $future_eventDate = $pickupDate->copy()->addDays(1);
        $future_eventParams = [
            'name' => 'Future Event',
            'start' => $future_eventDate->format('Y-m-d H:i:s'),
            'end' => $future_eventDate->addHour()->format('Y-m-d H:i:s'),
        ];
        $future_event = $I->createEvents($this->region['id'], $this->user['id'], $future_eventParams);
        $I->addEventInvitation($future_event['id'], $this->user['id'], [
            'status' => InvitationStatus::ACCEPTED
        ]);

        // Create a future event for the current user
        $multi_eventDate = $pickupDate->copy()->subDays(3);
        $multi_eventParams = [
            'name' => 'Test multi_Event',
            'start' => $multi_eventDate->format('Y-m-d H:i:s'),
            'end' => $multi_eventDate->addDays(5)->format('Y-m-d H:i:s'),
        ];
        $multi_event = $I->createEvents($this->region['id'], $this->user['id'], $multi_eventParams);
        $I->addEventInvitation($multi_event['id'], $this->user['id'], [
            'status' => InvitationStatus::INVITED
        ]);

        $I->login($this->user['email']);
        $I->sendGET('api/foodsaver/' . $this->user['id'] . '/agenda/' . $pickupDate->toDateString());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->canSeeResponseContainsJson([
            ['type' => 'store', 'id' => $this->store['id'], 'name' => $this->store['name'], 'isConfirmed' => true, 'date' => $past_pickupDate->format('Y-m-d H:i:s')],
            ['type' => 'store', 'id' => $this->store['id'], 'name' => $this->store['name'], 'isConfirmed' => true, 'date' => $pickupDate->format('Y-m-d H:i:s')],
            ['type' => 'event', 'id' => $event['id'], 'name' => $eventParams['name'], 'status' => 'accepted', 'date' => $eventParams['start'], 'end' => $eventParams['end']],
            ['type' => 'event', 'id' => $multi_event['id'], 'name' => $multi_eventParams['name'], 'status' => 'invited', 'date' => $multi_eventParams['start'], 'end' => $multi_eventParams['end']],
        ]);

        $I->dontSeeResponseContainsJson(['type' => 'event', 'id' => $past_event['id'], 'name' => $past_eventParams['name'], 'status' => 'maybe', 'date' => $past_eventParams['start']]);
        $I->dontSeeResponseContainsJson(['type' => 'event', 'id' => $future_event['id'], 'name' => $future_eventParams['name'], 'status' => 'accepted', 'date' => $future_eventParams['start']]);
    }
}
