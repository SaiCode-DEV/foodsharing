<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use DateInterval;
use DateTime;
use Foodsharing\Modules\Statistics\DTO\ActivityStatisticItem;
use Foodsharing\Modules\Statistics\DTO\PickupItem;
use Foodsharing\Modules\Statistics\DTO\StatisticsAgeBand;
use Foodsharing\Modules\Statistics\DTO\StatisticsGender;
use Foodsharing\Modules\Statistics\DTO\TotalStatisticItem;
use Foodsharing\Modules\Statistics\StatisticsGateway;
use Tests\Support\UnitTester;

class StatisticsGatewayTest extends Unit
{
    protected UnitTester $tester;
    private StatisticsGateway $gateway;
    private array $foodsaverOne;
    private array $foodsaverTwo;
    private array $foodsaverThree;
    private array $regionOne;
    private array $regionTwo;
    private array $regionThree;
    private array $storeOne;
    private array $storeTwo;
    private array $storeThree;
    private string $dateInterval1D;
    private string $dateInterval1M;
    private string $dateInterval4M;

    private const int INTERVAL_MONTH = 3;

    public function _before()
    {
        $birthday = (new DateTime())->sub(DateInterval::createFromDateString('19 year'));
        $this->dateInterval1D = (new DateTime())->sub(new DateInterval('P1D'))->format('Y-m-d');
        $this->dateInterval1M = (new DateTime())->sub(new DateInterval('P1M'))->format('Y-m-d');
        $this->dateInterval4M = (new DateTime())->sub(new DateInterval('P4M'))->format('Y-m-d');

        $this->gateway = $this->tester->get(StatisticsGateway::class);
        $this->regionOne = $this->tester->createRegion();
        $this->regionTwo = $this->tester->createRegion();
        $this->regionThree = $this->tester->createRegion();
        $this->storeOne = $this->tester->createStore($this->regionOne['id']);
        $this->storeTwo = $this->tester->createStore($this->regionTwo['id']);
        $this->storeThree = $this->tester->createStore($this->regionThree['id']);
        $this->foodsaverOne = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->regionOne['id'],
            'geb_datum' => $birthday,
            'stat_fetchweight' => 100,
            'stat_fetchcount' => 2,
        ]);
        $this->foodsaverTwo = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->regionTwo['id'],
            'geb_datum' => $birthday,
            'geschlecht' => 2,
            'stat_fetchweight' => 50,
            'stat_fetchcount' => 3,
        ]);
        $this->foodsaverThree = $this->tester->createFoodsaver(extra_params: [
            'bezirk_id' => $this->regionTwo['id'],
            'geb_datum' => $birthday,
            'geschlecht' => 3,
            'stat_fetchweight' => 25,
            'stat_fetchcount' => 1,
        ]);
    }

    public function testListOverallStatistic()
    {
        $totalStats = $this->gateway->listTotalStat();

        $expectedItem = TotalStatisticItem::create(
            33829400.50,
            2116647,
            7031,
            1004,
            74600,
            891
        );

        $this->assertInstanceOf(TotalStatisticItem::class, $totalStats);
        $this->assertEquals($expectedItem, $totalStats);
    }

    public function testListRegionStatistic()
    {
        $regionStat = $this->gateway->listStatRegions();

        $expectedItem = ActivityStatisticItem::create(
            'Arbeitsgruppen Überregional',
            5176.00,
            208
        );

        $this->assertIsArray($regionStat);
        $this->assertCount(1, $regionStat);
        $this->assertInstanceOf(ActivityStatisticItem::class, $regionStat[0]);
        $this->assertEquals($expectedItem, $regionStat[0]);
    }

    public function testListFoodsaverStatistic()
    {
        $foodsaverStat = $this->gateway->listStatFoodsaver();

        $expectedItem = ActivityStatisticItem::create(
            $this->foodsaverTwo['name'],
            $this->foodsaverTwo['stat_fetchweight'],
            $this->foodsaverTwo['stat_fetchcount']
        );

        $this->assertIsArray($foodsaverStat);
        $this->assertCount(3, $foodsaverStat);
        $this->assertInstanceOf(ActivityStatisticItem::class, $foodsaverStat[0]);
        $this->assertEquals($expectedItem, $foodsaverStat[0]);
    }

    public function testCountAllFoodsharers()
    {
        $countFoodsaver = $this->gateway->countAllFoodsharers();

        $this->assertIsInt($countFoodsaver);
        $this->assertEquals(3, $countFoodsaver);
    }

    public function testAvgDailyFetchCount()
    {
        for ($count = 100; $count < 200; ++$count) {
            $this->tester->haveInDatabase('fs_abholer', [
                'foodsaver_id' => $count,
                'betrieb_id' => $this->storeOne['id'],
                'date' => $this->dateInterval1D,
            ]);
        }

        $dailyCount = $this->gateway->avgDailyFetchCount();

        $this->assertIsInt($dailyCount);
        $this->assertEquals(1, $dailyCount);
    }

    public function testListFoodsaverStatisticCurrentMonth()
    {
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => (new DateTime())->format('Y-m-d'),
        ]);

        $foodsaverStats = $this->gateway->getFoodsaverStatsCurrentMonth();

        $expectedItem = PickupItem::create(
            $this->foodsaverOne['name'],
            1
        );

        $this->assertIsArray($foodsaverStats);
        $this->assertCount(1, $foodsaverStats);
        $this->assertInstanceOf(PickupItem::class, $foodsaverStats[0]);
        $this->assertEquals($expectedItem, $foodsaverStats[0]);
    }

    public function testListFoodsaverStatisticForDefinedInterval()
    {
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => (new DateTime())->format('Y-m-d'),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => $this->dateInterval1D,
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverTwo['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => $this->dateInterval1M,
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverThree['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => $this->dateInterval4M,
        ]);

        $foodsaverStats = $this->gateway->getFoodsaverStatsNumberMonths(self::INTERVAL_MONTH);

        $expectedItem = PickupItem::create(
            $this->foodsaverOne['name'],
            2
        );

        $this->assertIsArray($foodsaverStats);
        $this->assertCount(2, $foodsaverStats);
        $this->assertInstanceOf(PickupItem::class, $foodsaverStats[0]);
        $this->assertEquals($expectedItem, $foodsaverStats[0]);
    }

    public function testListRegionStatisticCurrentMonth()
    {
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => (new DateTime())->format('Y-m-d'),
        ]);

        $regionStats = $this->gateway->getRegionStatsCurrentMonth();

        $expectedItem = PickupItem::create(
            $this->regionOne['name'],
            1
        );

        $this->assertIsArray($regionStats);
        $this->assertCount(1, $regionStats);
        $this->assertInstanceOf(PickupItem::class, $regionStats[0]);
        $this->assertEquals($expectedItem, $regionStats[0]);
    }

    public function testListRegionStatisticForDefinedInterval()
    {
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => (new DateTime())->format('Y-m-d'),
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'betrieb_id' => $this->storeOne['id'],
            'date' => $this->dateInterval1D,
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverTwo['id'],
            'betrieb_id' => $this->storeTwo['id'],
            'date' => $this->dateInterval1M,
        ]);
        $this->tester->haveInDatabase('fs_abholer', [
            'foodsaver_id' => $this->foodsaverThree['id'],
            'betrieb_id' => $this->storeThree['id'],
            'date' => $this->dateInterval4M,
        ]);

        $regionStats = $this->gateway->getRegionStatsNumberMonth(self::INTERVAL_MONTH);

        $expectedItem = PickupItem::create(
            $this->regionOne['name'],
            2
        );

        $this->assertIsArray($regionStats);
        $this->assertCount(2, $regionStats);
        $this->assertInstanceOf(PickupItem::class, $regionStats[0]);
        $this->assertEquals($expectedItem, $regionStats[0]);
    }

    public function testCountAllBaskets()
    {
        $this->tester->haveInDatabase('fs_basket', [
            'foodsaver_id' => $this->foodsaverOne['id'],
            'description' => 'description',
        ]);
        $countBaskets = $this->gateway->countAllBaskets();

        $this->assertIsInt($countBaskets);
        $this->assertEquals(1, $countBaskets);
    }

    public function testAvgWeeklyBaskets()
    {
        for ($count = 100; $count < 200; ++$count) {
            $this->tester->haveInDatabase('fs_basket', [
                'foodsaver_id' => $count,
                'time' => $this->dateInterval1D,
                'description' => 'description',
            ]);
        }

        $dailyCount = $this->gateway->avgWeeklyBaskets();

        $this->assertIsInt($dailyCount);
        $this->assertEquals(25, $dailyCount);
    }

    public function testCountActiveFoodSharePoints()
    {
        $this->tester->haveInDatabase('fs_fairteiler', ['bezirk_id' => $this->regionOne['id'], 'status' => 1]);

        $countActivePoints = $this->gateway->countActiveFoodSharePoints();

        $this->assertIsInt($countActivePoints);
        $this->assertEquals(1, $countActivePoints);
    }

    public function testCountGenderRegion()
    {
        $countGender = $this->gateway->genderCountRegion($this->regionOne['id']);

        $this->assertIsArray($countGender);
        $this->assertCount(1, $countGender);
        $this->assertInstanceOf(StatisticsGender::class, $countGender[0]);

        $expectedGender = StatisticsGender::create($this->foodsaverOne['geschlecht'], 1);

        $this->assertIsArray($countGender);
        $this->assertCount(1, $countGender);
        $this->assertInstanceOf(StatisticsGender::class, $countGender[0]);
        $this->assertEquals($expectedGender, $countGender[0]);
    }

    public function testCountGenderHomeRegion()
    {
        $countGender = $this->gateway->genderCountHomeRegion($this->regionTwo['id']);

        $expectedGenderOne = StatisticsGender::create($this->foodsaverTwo['geschlecht'], 1);
        $expectedGenderTwo = StatisticsGender::create($this->foodsaverThree['geschlecht'], 1);

        $this->assertIsArray($countGender);
        $this->assertCount(2, $countGender);
        $this->assertInstanceOf(StatisticsGender::class, $countGender[0]);
        $this->assertEquals($expectedGenderOne, $countGender[0]);
        $this->assertEquals($expectedGenderTwo, $countGender[1]);
    }

    public function testAgeBandDistrict()
    {
        $ageBand = $this->gateway->ageBandHomeDistrict($this->regionOne['id']);

        $expectedAgeBand = StatisticsAgeBand::create('18-25', 1);

        $this->assertIsArray($ageBand);
        $this->assertCount(1, $ageBand);
        $this->assertInstanceOf(StatisticsAgeBand::class, $ageBand[0]);
        $this->assertEquals($expectedAgeBand, $ageBand[0]);
    }

    public function testAgeBandHomeDistrict()
    {
        $ageBand = $this->gateway->ageBandHomeDistrict($this->regionTwo['id']);

        $expectedAgeBand = StatisticsAgeBand::create('18-25', 2);

        $this->assertIsArray($ageBand);
        $this->assertCount(1, $ageBand);
        $this->assertInstanceOf(StatisticsAgeBand::class, $ageBand[0]);
        $this->assertEquals($expectedAgeBand, $ageBand[0]);
    }
}
