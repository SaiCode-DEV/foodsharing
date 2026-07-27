<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Region\RegionTimezoneResolver;
use Tests\Support\UnitTester;

class RegionTimezoneResolverTest extends Unit
{
    protected UnitTester $tester;
    private RegionTimezoneResolver $resolver;

    public function _before()
    {
        $this->resolver = $this->tester->get(RegionTimezoneResolver::class);
    }

    public function testRegionWithoutAnyTimezoneDefaultsToGermanTime(): void
    {
        $region = $this->tester->createRegion();

        $this->assertSame('Europe/Berlin', $this->resolver->timezoneNameFor($region['id']));
    }

    public function testOwnValueWins(): void
    {
        $region = $this->tester->createRegion(null, ['timezone' => 'Europe/Riga']);

        $this->assertSame('Europe/Riga', $this->resolver->timezoneNameFor($region['id']));
    }

    public function testChildInheritsFromClosestAncestor(): void
    {
        $country = $this->tester->createRegion(null, ['timezone' => 'Europe/Riga']);
        $city = $this->tester->createRegion(null, ['parent_id' => $country['id']]);
        $district = $this->tester->createRegion(null, ['parent_id' => $city['id']]);

        $this->assertSame('Europe/Riga', $this->resolver->timezoneNameFor($district['id']));
    }

    public function testOwnValueBeatsInheritedValue(): void
    {
        $country = $this->tester->createRegion(null, ['timezone' => 'Europe/Riga']);
        $city = $this->tester->createRegion(null, ['parent_id' => $country['id'], 'timezone' => 'Europe/Vienna']);

        $this->assertSame('Europe/Vienna', $this->resolver->timezoneNameFor($city['id']));
    }

    public function testInvalidValueFallsBackToGermanTime(): void
    {
        $region = $this->tester->createRegion(null, ['timezone' => 'Not/AZone']);

        $this->assertSame('Europe/Berlin', $this->resolver->timezoneNameFor($region['id']));
    }

    public function testUnknownAndMissingRegionsDefaultToGermanTime(): void
    {
        $this->assertSame('Europe/Berlin', $this->resolver->timezoneNameFor(99999999));
        $this->assertSame('Europe/Berlin', $this->resolver->timezoneNameFor(null));
    }

    public function testStoreInheritsTheTimezoneOfItsRegion(): void
    {
        $country = $this->tester->createRegion(null, ['timezone' => 'Europe/Riga']);
        $city = $this->tester->createRegion(null, ['parent_id' => $country['id']]);
        $store = $this->tester->createStore($city['id']);

        $this->assertSame('Europe/Riga', $this->resolver->timezoneNameForStore($store['id']));
        $this->assertSame('Europe/Berlin', $this->resolver->timezoneNameForStore(99999999));
    }
}
