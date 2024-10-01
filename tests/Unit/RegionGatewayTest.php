<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Region\RegionGateway;
use Tests\Support\UnitTester;

class RegionGatewayTest extends Unit
{
    protected UnitTester $tester;
    private RegionGateway $gateway;
    private array $foodsaver;
    private array $region;
    private array $childRegion;
    private array $childChildRegion;

    public function _before()
    {
        $this->gateway = $this->tester->get(RegionGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->region = $this->tester->createRegion('God', fillMailbox: false);
        $this->tester->addRegionMember($this->region['id'], $this->foodsaver['id']);
        $this->childRegion = $this->tester->createRegion('Jesus', ['parent_id' => $this->region['id']], false);
        $this->childChildRegion = $this->tester->createRegion('Human', ['parent_id' => $this->childRegion['id']]);
    }

    public function testGetAllRegions(): void
    {
        $regions = $this->gateway->listIdsForFoodsaverWithDescendants($this->foodsaver['id']);
        $this->assertEquals(3, count($regions));
        $this->assertEquals([$this->region['id'], $this->childRegion['id'], $this->childChildRegion['id']], $regions);
    }

    public function testGetRegions(): void
    {
        $regions = $this->gateway->listForFoodsaver($this->foodsaver['id']);
        $this->assertEquals(1, count($regions));
        $this->assertEquals([$this->region['id']], array_keys($regions));
        $this->assertEquals([
            'id' => $this->region['id'],
            'name' => $this->region['name'],
            'type' => $this->region['type'],
            'parent_id' => $this->region['parent_id'],
        ], $regions[$this->region['id']]);
    }

    public function testGetDescendantsAndSelf(): void
    {
        $regions = $this->gateway->listIdsForDescendantsAndSelf($this->region['id']);
        $this->assertEquals(3, count($regions));
        $this->assertEquals([$this->region['id'], $this->childRegion['id'], $this->childChildRegion['id']], $regions);
    }

    public function testGetDescendantsAndSelfWithoutSelf(): void
    {
        $regions = $this->gateway->listIdsForDescendantsAndSelf($this->region['id'], false);
        $this->assertEquals(2, count($regions));
        $this->assertEquals([$this->childRegion['id'], $this->childChildRegion['id']], $regions);
    }

    public function testListRegionsIncludingParents(): void
    {
        $regions = $this->gateway->listRegionsIncludingParents([$this->childRegion['id']]);
        $this->assertEquals([$this->region['id'], $this->childRegion['id']], $regions);
    }
}
