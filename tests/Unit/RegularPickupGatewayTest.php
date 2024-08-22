<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Codeception\Test\Unit;
use Foodsharing\Modules\Store\DTO\RegularPickup;
use Foodsharing\Modules\Store\RegularPickupGateway;
use Tests\Support\UnitTester;

class RegularPickupGatewayTest extends Unit
{
    protected UnitTester $tester;
    private RegularPickupGateway $gateway;

    private array $store;
    private array $region;

    public function _before()
    {
        $this->gateway = $this->tester->get(RegularPickupGateway::class);
        $this->region = $this->tester->createRegion(fillMailbox: false);
        $this->store = $this->tester->createStore($this->region['id']);
    }

    public function testGetRegularPickupSettings(): void
    {
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $pickup_1->startTimeOfPickup, 'dow' => $pickup_1->weekday, 'fetcher' => $pickup_1->maxCountOfSlots]
        );

        $pickup_2 = new RegularPickup();
        $pickup_2->weekday = 3;
        $pickup_2->startTimeOfPickup = '16:50:00';
        $pickup_2->maxCountOfSlots = 4;
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $pickup_2->startTimeOfPickup, 'dow' => $pickup_2->weekday, 'fetcher' => $pickup_2->maxCountOfSlots]
        );

        $regularSlots = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals([
            $pickup_2,
            $pickup_1
        ], $regularSlots);
    }

    public function testUpdateRegularPickupForANewStore(): void
    {
        $pickup = new RegularPickup();
        $pickup->weekday = 4;
        $pickup->startTimeOfPickup = '16:40:00';
        $pickup->maxCountOfSlots = 2;
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $pickup->startTimeOfPickup, 'dow' => $pickup->weekday, 'fetcher' => $pickup->maxCountOfSlots]
        );

        $pickup_max_slot_count = new RegularPickup();
        $pickup_max_slot_count->weekday = 4;
        $pickup_max_slot_count->startTimeOfPickup = '16:40:00';
        $pickup_max_slot_count->maxCountOfSlots = 4;

        $this->gateway->insertOrUpdateRegularPickup($this->store['id'], $pickup_max_slot_count);

        $regularSlots = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals([
            $pickup_max_slot_count
        ], $regularSlots);
    }

    public function testInsertRegularPickupForANewStore(): void
    {
        $regularSlots = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals([], $regularSlots);

        $pickup = new RegularPickup();
        $pickup->weekday = 4;
        $pickup->startTimeOfPickup = '16:40:00';
        $pickup->maxCountOfSlots = 2;

        $this->gateway->insertOrUpdateRegularPickup($this->store['id'], $pickup);

        $regularSlots = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals([
            $pickup
        ], $regularSlots);
    }

    public function deleteAllRegularPickups($storeId): void
    {
        $pickup_1 = new RegularPickup();
        $pickup_1->weekday = 4;
        $pickup_1->startTimeOfPickup = '16:40:00';
        $pickup_1->maxCountOfSlots = 2;
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $pickup_1->startTimeOfPickup, 'dow' => $pickup_1->weekday, 'fetcher' => $pickup_1->maxCountOfSlots]
        );

        $pickup_2 = new RegularPickup();
        $pickup_2->weekday = 3;
        $pickup_2->startTimeOfPickup = '16:50:00';
        $pickup_2->maxCountOfSlots = 4;
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $pickup_2->startTimeOfPickup, 'dow' => $pickup_2->weekday, 'fetcher' => $pickup_2->maxCountOfSlots]
        );

        $this->gateway->deleteAllRegularPickups($this->store['id']);

        $regularSlots = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals([], $regularSlots);
    }

    private function testRegularPickupRangeByStartDay(Carbon $startDate): void
    {
        $date1HourBefore = $startDate->copy()->setTimezone('Europe/Berlin')->subHour();
        $dateOneDayAfter = $startDate->copy()->setTimezone('Europe/Berlin')->addDay();
        $dateTwoDayWith10MinAfter = $startDate->copy()->setTimezone('Europe/Berlin')->addDays(2)->addMinutes(10);
        $time = $startDate->copy()->setTimezone('Europe/Berlin')->format('H:i:s');
        $timePlus2Min = $startDate->copy()->setTimezone('Europe/Berlin')->addMinutes(2)->format('H:i:s');
        $weekday = $startDate->copy()->setTimezone('Europe/Berlin')->format('w');
        $weekdayOneDayAfter = $startDate->copy()->setTimezone('Europe/Berlin')->addDay()->format('w');
        $weekdayThreeDayAfter = $startDate->copy()->setTimezone('Europe/Berlin')->addDays(3)->format('w');

        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $time, 'dow' => $weekday, 'fetcher' => 1]
        );
        $this->tester->addRecurringPickup($this->store['id'],
            ['time' => $time, 'dow' => $weekdayThreeDayAfter, 'fetcher' => 1]
        );
        $this->tester->addRecurringPickup($this->store['id'], ['time' => $timePlus2Min, 'dow' => $weekdayOneDayAfter, 'fetcher' => 1]);

        $resultsAll = $this->gateway->getRegularPickup($this->store['id']);
        $this->assertEquals(3, count($resultsAll));

        $results = $this->gateway->getRegularPickupsForRange($this->store['id'], $date1HourBefore, $dateOneDayAfter);
        $this->assertEquals(1, count($results));

        $results = $this->gateway->getRegularPickupsForRange($this->store['id'], $date1HourBefore, $dateTwoDayWith10MinAfter);
        $this->assertEquals(2, count($results));
    }

    public function testGetRegularPickupsRangeAtEndOfWeek(): void
    {
        $expectedIsoDate = '2022-12-18T10:35:00Z';
        $startDate = Carbon::createFromFormat(DATE_ATOM, $expectedIsoDate);
        $this->testRegularPickupRangeByStartDay($startDate);
    }

    public function testGetRegularPickupsRangeAtMidOfWeek(): void
    {
        $expectedIsoDate = '2022-12-21T10:35:00Z';
        $startDate = Carbon::createFromFormat(DATE_ATOM, $expectedIsoDate);
        $this->testRegularPickupRangeByStartDay($startDate);
    }

    private function checkGetRegularPickupsInRange($start, $end, $expectedCount): void
    {
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $start);
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $end);
        $pickups = $this->gateway->getRegularPickupsForRange($this->store['id'], $startDate, $endDate);
        $this->assertEquals($expectedCount, count($pickups));
    }

    public function testCliInput(): void
    {
        $startDate = Carbon::now();
        $endDate = $startDate->clone()->addDays(2);

        $store_established = $this->tester->createStore(1, null, null, ['betrieb_status_id' => '5']);
        $todayDow = intval(date('w'));
        $nextDow = ($todayDow + 1) % 7;
        $this->tester->addRecurringPickup($store_established['id'], ['dow' => $nextDow]);

        $pickups = $this->gateway->getRegularPickupsForRange($store_established['id'], $startDate, $endDate);

        $this->assertEquals(1, count($pickups));
    }

    public function testGetRegularPickupsInRangeDaySo(): void
    {
        $storeId = $this->store['id'];
        $this->tester->addRecurringPickup($storeId, ['time' => '10:10:00', 'dow' => 0, 'fetcher' => 2]);

        // Test before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 00:01:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 10:09:00', 0);

        // Test exact end is included
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 10:10:00', 1);

        // Test end moves after pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 10:11:00', 1);

        // Test start moves short before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 10:09:59', '2022-12-18 10:15:00', 1);
        $this->checkGetRegularPickupsInRange('2022-12-18 10:10:00', '2022-12-18 10:15:00', 1);

        // Test start moves after before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 10:10:01', '2022-12-18 10:15:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-18 10:11:00', '2022-12-18 10:15:00', 0);

        // Test ~ one week
        $this->checkGetRegularPickupsInRange('2022-12-18 10:11:00', '2022-12-25 10:09:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-18 10:11:00', '2022-12-25 10:11:00', 1);
        $this->checkGetRegularPickupsInRange('2022-12-18 10:11:00', '2022-12-25 10:12:00', 1);

        // Test ~ two week
        $this->checkGetRegularPickupsInRange('2022-12-19 10:11:00', '2023-01-01 10:12:00', 1);
    }

    public function testGetRegularPickupsInRangeDayDi(): void
    {
        $storeId = $this->store['id'];
        $this->tester->addRecurringPickup($storeId, ['time' => '10:10:00', 'dow' => 2, 'fetcher' => 2]);

        // Test before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-20 00:01:00', '2022-12-20 00:01:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-20 00:01:00', '2022-12-20 10:09:00', 0);

        // Test exact end is included
        $this->checkGetRegularPickupsInRange('2022-12-20 00:01:00', '2022-12-20 10:10:00', 1);

        // Test end moves after pickup time
        $this->checkGetRegularPickupsInRange('2022-12-20 00:01:00', '2022-12-20 10:11:00', 1);

        // Test start moves short before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-20 10:09:59', '2022-12-20 10:15:00', 1);
        $this->checkGetRegularPickupsInRange('2022-12-20 10:10:00', '2022-12-20 10:15:00', 1);

        // Test start moves after before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-20 10:10:01', '2022-12-20 10:15:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-20 10:11:00', '2022-12-20 10:15:00', 0);

        // Test ~ one week
        $this->checkGetRegularPickupsInRange('2022-12-20 10:11:00', '2022-12-27 10:09:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-20 10:11:00', '2022-12-27 10:11:00', 1);
        $this->checkGetRegularPickupsInRange('2022-12-20 10:11:00', '2022-12-27 10:12:00', 1);

        // Test ~ two week
        $this->checkGetRegularPickupsInRange('2022-12-19 10:11:00', '2023-01-03 10:12:00', 1);
    }

    public function testGetRegularPickupsInRangeDaySa(): void
    {
        $storeId = $this->store['id'];
        $this->tester->addRecurringPickup($storeId, ['time' => '10:10:00', 'dow' => 6, 'fetcher' => 2]);

        // Test before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 00:01:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-18 10:09:00', 0);

        // Test exact end is included
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-24 10:10:00', 1);

        // Test end moves after pickup time
        $this->checkGetRegularPickupsInRange('2022-12-18 00:01:00', '2022-12-24 10:11:00', 1);

        // Test start moves short before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-24 10:09:59', '2022-12-24 10:15:00', 1);
        $this->checkGetRegularPickupsInRange('2022-12-24 10:10:00', '2022-12-24 10:15:00', 1);

        // Test start moves after before pickup time
        $this->checkGetRegularPickupsInRange('2022-12-24 10:10:01', '2022-12-24 10:15:00', 0);
        $this->checkGetRegularPickupsInRange('2022-12-24 10:11:00', '2022-12-24 10:15:00', 0);

        // Test ~ two week
        $this->checkGetRegularPickupsInRange('2022-12-18 10:11:00', '2023-01-01 10:12:00', 1);

        $this->checkGetRegularPickupsInRange('2022-12-23 10:10:00', '2022-12-25 10:12:00', 1);
    }

    public function testGetRegularPickupsInRangeDaySpecialt(): void
    {
        $date = '2022-12-18';
        $time = '16:40:00';
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:01');
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 16:50:02')->addWeeks(10);
        $dayOfWeek = intval($startDate->clone()->addDays(1)->format('w'));

        $storeId = $this->store['id'];
        $this->tester->addRecurringPickup($storeId, ['time' => $time, 'dow' => $dayOfWeek, 'fetcher' => 2]);

        $this->checkGetRegularPickupsInRange($startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'), 1);
    }

    private function checkRangeOfGeneration($regularPickup, $start, $end, $expectedCount): void
    {
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $start);
        $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $end);
        $pickups = $regularPickup->convertToOneTimePickups($startDate, $endDate);
        $this->assertEquals($expectedCount, count($pickups));
    }

    public function testConvertRegularPickup(): void
    {
        $regularPickup = new RegularPickup();
        $regularPickup->weekday = 1; // Montag
        $regularPickup->startTimeOfPickup = '01:00:00';
        $regularPickup->maxCountOfSlots = 9;

        // Test before pickup time
        $this->checkRangeOfGeneration($regularPickup, '2022-12-20 00:01:00', '2022-12-22 10:09:00', 0);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-19 10:09:00', 1);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2023-03-10 10:09:00', 12);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-18 00:01:00', 0);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-20 10:09:00', 1);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-25 10:09:00', 1);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-26 10:09:00', 2);
        $this->checkRangeOfGeneration($regularPickup, '2022-12-18 00:01:00', '2022-12-27 10:09:00', 2);
    }
}
