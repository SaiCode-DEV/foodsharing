<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Unit\UnitGateway;
use Tests\Support\UnitTester;

class UnitGatewayTest extends Unit
{
    protected UnitTester $tester;
    private UnitGateway $gateway;
    private array $foodsaver;
    private array $foodsaver2;
    private array $country;
    private array $federalState;
    private array $bigCity;
    private array $city;
    private array $city2;
    private array $group;
    private array $group2;

    final public function _before(): void
    {
        $this->gateway = $this->tester->get(UnitGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->foodsaver2 = $this->tester->createFoodsaver();

        // Create region hierarchies
        $this->country = $this->tester->createRegion('Germany', ['parent_id' => RegionIDs::EUROPE, 'type' => UnitType::COUNTRY]);
        $this->federalState = $this->tester->createRegion('Baden-Württenberg', ['parent_id' => $this->country['id'], 'type' => UnitType::FEDERAL_STATE]);
        $this->bigCity = $this->tester->createRegion('Mannheim', ['parent_id' => $this->federalState['id'], 'type' => UnitType::BIG_CITY]);
        $this->city = $this->tester->createRegion('Neckarstadt', ['parent_id' => $this->bigCity['id'], 'type' => UnitType::CITY]);
        $this->city2 = $this->tester->createRegion('Ladenburg', ['parent_id' => $this->federalState['id'], 'type' => UnitType::CITY]);

        // Group
        $this->group = $this->tester->createWorkingGroup('Unit test AG');
        $this->group2 = $this->tester->createWorkingGroup('Teaching AG');

        // Allocate users to region and groups
        $this->tester->addRegionMember($this->bigCity['id'], $this->foodsaver['id']);
        $this->tester->addRegionMember($this->city['id'], $this->foodsaver['id']);
        $this->tester->addRegionMember($this->city2['id'], $this->foodsaver['id']);
        $this->tester->addRegionAdmin($this->city['id'], $this->foodsaver['id']);

        $this->tester->addRegionMember($this->city2['id'], $this->foodsaver2['id']);
        $this->tester->addRegionMember($this->group['id'], $this->foodsaver2['id']);
        $this->tester->addRegionMember($this->group2['id'], $this->foodsaver2['id']);

        $this->tester->addRegionAdmin($this->group['id'], $this->foodsaver2['id']);
    }

    final public function testGetRegions(): void
    {
        $regions = $this->gateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($this->foodsaver['id'], UnitType::getRegionTypes());
        $this->assertEquals(3, count($regions));

        $this->assertEquals($this->city['id'], $regions[0]->unit->id);
        $this->assertEquals($this->city['name'], $regions[0]->unit->name);
        $this->assertEquals(UnitType::CITY, $regions[0]->unit->type);
        $this->assertTrue($regions[0]->isResponsible);

        $this->assertEquals($this->city2['id'], $regions[1]->unit->id);
        $this->assertEquals($this->city2['name'], $regions[1]->unit->name);
        $this->assertEquals(UnitType::CITY, $regions[1]->unit->type);
        $this->assertFalse($regions[1]->isResponsible);

        $this->assertEquals($this->bigCity['id'], $regions[2]->unit->id);
        $this->assertEquals($this->bigCity['name'], $regions[2]->unit->name);
        $this->assertEquals(UnitType::BIG_CITY, $regions[2]->unit->type);
        $this->assertFalse($regions[2]->isResponsible);
    }

    final public function testGetOnlyCityRegions(): void
    {
        $regions = $this->gateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($this->foodsaver['id'], [UnitType::CITY]);
        $this->assertEquals(2, count($regions));

        $this->assertEquals($this->city['id'], $regions[0]->unit->id);
        $this->assertEquals($this->city['name'], $regions[0]->unit->name);
        $this->assertEquals(UnitType::CITY, $regions[0]->unit->type);
        $this->assertTrue($regions[0]->isResponsible);

        $this->assertEquals($this->city2['id'], $regions[1]->unit->id);
        $this->assertEquals($this->city2['name'], $regions[1]->unit->name);
        $this->assertEquals(UnitType::CITY, $regions[1]->unit->type);
        $this->assertFalse($regions[1]->isResponsible);
    }

    final public function testGetAllRegions(): void
    {
        $regions = $this->gateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($this->foodsaver['id'], [UnitType::CITY]);
        $this->assertEquals(2, count($regions));
    }

    final public function testGetGroups(): void
    {
        $groups = $this->gateway->listAllDirectReleatedUnitsAndResponsibilitiesOfFoodsaver($this->foodsaver2['id'], UnitType::getGroupTypes());
        $this->assertEquals(2, count($groups));
        $this->assertEquals($this->group['id'], $groups[0]->unit->id);
        $this->assertEquals($this->group['name'], $groups[0]->unit->name);
        $this->assertEquals(UnitType::WORKING_GROUP, $groups[0]->unit->type);
        $this->assertTrue($groups[0]->isResponsible);

        $this->assertEquals($this->group2['id'], $groups[1]->unit->id);
        $this->assertEquals($this->group2['name'], $groups[1]->unit->name);
        $this->assertEquals(UnitType::WORKING_GROUP, $groups[1]->unit->type);
        $this->assertFalse($groups[1]->isResponsible);
    }
}
