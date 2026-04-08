<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Core\DBConstants\Store\StoreLogAction;
use Foodsharing\Modules\Store\PickupGateway;
use Foodsharing\Modules\Store\RegularPickupGateway;
use Tests\Support\UnitTester;

class PickupGatewayTest extends Unit
{
    protected UnitTester $tester;
    private PickupGateway $gateway;

    private array $store;
    private array $foodsaver;
    private array $region;

    public function _before()
    {
        $this->regularPickupGateway = $this->tester->get(RegularPickupGateway::class);
        $this->gateway = $this->tester->get(PickupGateway::class);
        $this->region = $this->tester->createRegion(fillMailbox: false);
        $this->store = $this->tester->createStore($this->region['id']);
        $this->foodsaver = $this->tester->createFoodsaver();
    }

    public function testGetPickupSignupsForDates(): void
    {
        $date = '2018-07-18';
        $time = '16:40:00';
        $datetime = $date . ' ' . $time;
        $dow = 3; /* above date is a wednesday */
        $fetcher = 2;
        $fsid = $this->foodsaver['id'];
        $signupDate = (new Carbon($datetime))->subDays(3);

        $addStorelog = function (int $userId, int $action, Carbon $activityDate) use ($datetime) {
            $this->tester->addStoreLog($this->store['id'], $userId, $userId, $action, [
                'date_activity' => $activityDate->format('Y-m-d H:i:s'),
                'date_reference' => $datetime,
            ]);
        };

        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $time, 'dow' => $dow, 'fetcher' => $fetcher]
        );
        $this->gateway->addFetcher($fsid, $this->store['id'], new Carbon($datetime));
        $addStorelog($fsid, StoreLogAction::SIGN_UP_SLOT, $signupDate);

        $fsList = $this->gateway->getSignedUpPickupsForDate($this->store['id'], new Carbon($datetime));
        $this->assertEquals(1, count($fsList));
        $this->assertEquals($fsid, $fsList[0]->foodsaverId);
        $this->assertEquals(new Carbon($datetime), $fsList[0]->date);
        $this->assertEquals(false, $fsList[0]->isConfirmed);

        // Create a second user, who will also sign up for the same pickup.
        $otherFoodsaver = $this->tester->createFoodsaver();

        // Add a previous signup for the second user to the storelog, with an earlier signup date.
        // This simulates the case where the user first signed up for the pickup, then canceled it.
        $earlierSignupDate = $signupDate->copy()->subDay();
        $addStorelog($otherFoodsaver['id'], StoreLogAction::SIGN_UP_SLOT, $earlierSignupDate);
        $addStorelog($otherFoodsaver['id'], StoreLogAction::SIGN_OUT_SLOT, $earlierSignupDate->copy()->addHour());

        // Add a signup for the second user with a later signup date.
        $laterSignupDate = $signupDate->copy()->addDay();
        $this->tester->addPicker($this->store['id'], $otherFoodsaver['id'], ['date' => $datetime]);
        $addStorelog($otherFoodsaver['id'], StoreLogAction::SIGN_UP_SLOT, $laterSignupDate);

        // Check both current signups are returned in the correct order.
        $fsList = $this->gateway->getSignedUpPickupsForDate($this->store['id'], new Carbon($datetime));
        $this->assertEquals(2, count($fsList));
        $this->assertEquals($fsid, $fsList[0]->foodsaverId);
        $this->assertEquals($otherFoodsaver['id'], $fsList[1]->foodsaverId);
    }

    public function testGetIrregularPickupDate(): void
    {
        $expectedIsoDate = '2018-07-19T10:35:00Z';
        $fetcher = 1;
        $internalDate = Carbon::createFromFormat(DATE_ATOM, $expectedIsoDate);
        $date = $internalDate->copy()->setTimezone('Europe/Berlin')->format('Y-m-d H:i:s');
        $this->tester->addPickup($this->store['id'], ['time' => $date, 'fetchercount' => $fetcher]);
        $irregularSlots = $this->gateway->getOnetimePickups($this->store['id'], $internalDate);

        $this->assertEquals(1, count($irregularSlots));

        $this->assertEquals($fetcher, $irregularSlots[0]->slots);
        $this->assertEquals($internalDate->copy()->setTimezone('Europe/Berlin'), $irregularSlots[0]->date);
    }

    public function testUpdateExpiredBellsRemovesBellIfNoUnconfirmedFetchesAreInTheFuture(): void
    {
        $foodsaver = $this->tester->createFoodsaver();

        $this->gateway->addFetcher($foodsaver['id'], $this->store['id'], new Carbon('1970-01-01'));

        $this->tester->updateInDatabase(
            'fs_bell',
            ['expiration' => '1970-01-01'],
            ['identifier' => 'store-fetch-unconfirmed-' . $this->store['id']]
        ); // outdate bell notification

        $this->gateway->updateExpiredBells();

        $this->tester->dontSeeInDatabase('fs_bell', ['identifier' => 'store-fetch-unconfirmed-' . $this->store['id']]);
    }

    public function testGetPickupHistory(): void
    {
        $pickupDate = Carbon::now()->subDays(14)->setHour(12)->setMinute(0)->setSecond(0)->setMicrosecond(0);
        $firstSignupDate = $pickupDate->copy()->subHours(6);
        $leaveDate = $pickupDate->copy()->subHours(5);
        $secondSignupDate = $pickupDate->copy()->subHours(4);

        // Create a pickup in the past
        $this->tester->addPickup($this->store['id'], ['time' => $pickupDate]);

        // Sign up for it with a store log entry
        $this->gateway->addFetcher($this->foodsaver['id'], $this->store['id'], $pickupDate);
        $this->tester->addStoreLog($this->store['id'], $this->foodsaver['id'], null, StoreLogAction::SIGN_UP_SLOT, [
            'date_reference' => $pickupDate,
            'date_activity' => $firstSignupDate,
        ]);

        // Leave it with a store log entry
        $this->gateway->removeFetcher($this->foodsaver['id'], $this->store['id'], $pickupDate);
        $this->tester->addStoreLog($this->store['id'], $this->foodsaver['id'], null, StoreLogAction::SIGN_OUT_SLOT, [
            'date_reference' => $pickupDate,
            'date_activity' => $leaveDate,
        ]);

        // Sign up for it again with another store log entry
        $this->gateway->addFetcher($this->foodsaver['id'], $this->store['id'], $pickupDate);
        $this->tester->addStoreLog($this->store['id'], $this->foodsaver['id'], null, StoreLogAction::SIGN_UP_SLOT, [
            'date_reference' => $pickupDate,
            'date_activity' => $secondSignupDate,
        ]);

        $history = $this->gateway->getPickupHistory(
            $this->store['id'],
            $pickupDate->copy()->subDay(),
            $pickupDate->copy()->addDay()
        );

        // Only the last signup should be returned
        $this->assertCount(1, $history);
        $this->assertEquals($this->foodsaver['id'], $history[0]['foodsaverId']);
        $this->assertEquals($pickupDate->format('Y-m-d H:i:s'), $history[0]['date']);
        $this->assertEquals($secondSignupDate->format('Y-m-d H:i:s'), $history[0]['signUpDate']);
    }
}
