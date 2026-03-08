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

        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $time, 'dow' => $dow, 'fetcher' => $fetcher]
        );
        $this->gateway->addFetcher($fsid, $this->store['id'], new Carbon($datetime));

        $fsList = $this->gateway->getSignedUpPickupsForDate($this->store['id'], new Carbon($datetime));
        $this->assertEquals(1, count($fsList));
        $this->assertEquals($fsid, $fsList[0]->foodsaverId);
        $this->assertEquals(new Carbon($datetime), $fsList[0]->date);
        $this->assertEquals(false, $fsList[0]->isConfirmed);

        // Add another signup for the same pickup date, but with a later signup date (in the future)
        $otherFoodsaver = $this->tester->createFoodsaver();
        $laterSignupDate = (new Carbon($datetime))->addDay();
        $this->tester->addPicker($this->store['id'], $otherFoodsaver['id'], ['date' => $datetime]);
        $this->tester->addStoreLog(
            $this->store['id'],
            $otherFoodsaver['id'],
            $otherFoodsaver['id'],
            StoreLogAction::SIGN_UP_SLOT, [
                'date_activity' => $laterSignupDate->format('Y-m-d H:i:s'),
                'date_reference' => $datetime,
            ],
        );

        // Check both are returned in the correct order
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
}
