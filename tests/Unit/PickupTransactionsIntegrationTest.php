<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Store\PickupTransactions;
use Tests\Support\UnitTester;

class PickupTransactionsIntegrationTest extends Unit
{
    protected UnitTester $tester;
    private PickupTransactions $transactions;
    private array $store;

    public function _before()
    {
        $this->transactions = $this->tester->get(PickupTransactions::class);
        $region = $this->tester->createRegion(fillMailbox: false);
        $this->store = $this->tester->createStore($region['id']);
    }

    public function testRegularPickupsInRange(): void
    {
        $date = '2022-12-18';
        $time = '16:40:00';
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:01');
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:02')->addWeeks(10);
        $dayOfWeek = intval($startDate->clone()->addDays(1)->format('w'));
        $this->tester->addRecurringPickup($this->store['id'], ['time' => $time, 'dow' => $dayOfWeek, 'fetcher' => 1]);

        $pickups = $this->transactions->getAllPickupsInRange($this->store['id'], $startDate, $endDate);
        $this->assertEquals(10, count($pickups));

        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->format('c'), $pickups[0]->date->format('c'));
        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->addWeeks(1)->format('c'), $pickups[1]->date->format('c'));
        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->addWeeks(2)->format('c'), $pickups[2]->date->format('c'));

        foreach ($pickups as $pickup) {
            $this->assertEquals(1, $pickup->slots);
        }
    }

    public function testOverrideRegularPickupsByOneTimePickupInRange(): void
    {
        $date = '2022-12-18';
        $time = '16:40:00';
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:01');
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:02')->addWeeks(10);
        $dayOfWeek = intval($startDate->clone()->addDays(1)->format('w'));
        $this->tester->addRecurringPickup($this->store['id'], ['time' => $time, 'dow' => $dayOfWeek, 'fetcher' => 1]);

        $this->tester->addPickup($this->store['id'], ['time' => Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time)->addDays(1), 'fetchercount' => 0]);

        $pickups = $this->transactions->getAllPickupsInRange($this->store['id'], $startDate, $endDate);
        $this->assertEquals(10, count($pickups));

        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->format('c'), $pickups[0]->date->format('c'));
        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->addWeeks(1)->format('c'), $pickups[1]->date->format('c'));
        $this->assertEquals(Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:40:00')->addDays(1)->addWeeks(2)->format('c'), $pickups[2]->date->format('c'));

        foreach ($pickups as $key => $pickup) {
            if ($key == 0) {
                $this->assertEquals(0, $pickup->slots);
            } else {
                $this->assertEquals(1, $pickup->slots);
            }
        }
    }

    public function testCliInput(): void
    {
        $startDate = Carbon::now();
        $endDate = $startDate->clone()->addDays(2);

        $store = $this->tester->createStore(1);
        $store_established = $this->tester->createStore(1, null, null, ['betrieb_status_id' => '5']);

        $fetcher_unconfirmed_past_1 = $this->tester->createFoodsaver();
        $fetcher_unconfirmed_past_2 = $this->tester->createFoodsaver();
        $fetcher_unconfirmed_future = $this->tester->createFoodsaver();

        $fetcher_confirmed_past = $this->tester->createFoodsaver();
        $fetcher_confirmed_future = $this->tester->createFoodsaver();

        $store_manager_1 = $this->tester->createFoodsaver();

        $this->tester->addStoreTeam($store['id'], $fetcher_unconfirmed_past_1['id'], false, false, true);
        $this->tester->addStoreTeam($store['id'], $fetcher_unconfirmed_past_2['id'], false, false, true);
        $this->tester->addStoreTeam($store['id'], $fetcher_unconfirmed_future['id'], false, false, true);

        $this->tester->addStoreTeam($store['id'], $fetcher_confirmed_past['id'], false, false, true);
        $this->tester->addStoreTeam($store['id'], $fetcher_confirmed_future['id'], false, false, true);

        $this->tester->addStoreTeam($store_established['id'], $store_manager_1['id'], true, false, true);
        $todayDow = intval(date('w'));
        $nextDow = ($todayDow + 1) % 7;
        $this->tester->addRecurringPickup($store_established['id'], ['dow' => $nextDow]);

        $dataset_unconfirmed_past_1 = ['foodsaver_id' => $fetcher_unconfirmed_past_1['id'], 'betrieb_id' => $store['id'], 'date' => '2001-02-25 08:55', 'confirmed' => 0];
        $this->tester->haveInDatabase('fs_abholer', $dataset_unconfirmed_past_1);

        $dataset_unconfirmed_past_2 = ['foodsaver_id' => $fetcher_unconfirmed_past_2['id'], 'betrieb_id' => $store['id'], 'date' => '2008-08-25 17:55', 'confirmed' => 0];
        $this->tester->haveInDatabase('fs_abholer', $dataset_unconfirmed_past_2);

        $dataset_unconfirmed_future = ['foodsaver_id' => $fetcher_unconfirmed_future['id'], 'betrieb_id' => $store['id'], 'date' => '2500-06-25 22:20', 'confirmed' => 0];
        $this->tester->haveInDatabase('fs_abholer', $dataset_unconfirmed_future);

        $dataset_confirmed_past = ['foodsaver_id' => $fetcher_confirmed_past['id'], 'betrieb_id' => $store['id'], 'date' => '2008-11-25 17:55', 'confirmed' => 1];
        $this->tester->haveInDatabase('fs_abholer', $dataset_confirmed_past);

        $dataset_confirmed_future = ['foodsaver_id' => $fetcher_confirmed_future['id'], 'betrieb_id' => $store['id'], 'date' => '2500-05-25 22:20', 'confirmed' => 1];
        $this->tester->haveInDatabase('fs_abholer', $dataset_confirmed_future);

        $pickups = $this->transactions->getAllPickupsInRange($store_established['id'], $startDate, $endDate);

        $this->assertEquals(1, count($pickups));
    }
}
