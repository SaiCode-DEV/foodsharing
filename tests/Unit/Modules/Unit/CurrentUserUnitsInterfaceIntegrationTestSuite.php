<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Tests\Support\UnitTester;

abstract class CurrentUserUnitsInterfaceIntegrationTestSuite extends Unit
{
    protected UnitTester $tester;

    protected $foodsaverGateway;
    protected $regionGateway;
    protected $session;

    protected $masterRegion;
    protected $homeRegion;
    protected $unrelatedRegion;
    protected $relatedRegionWithUnrelatedParent;
    protected $relatedRegion;
    protected $relatedAdminRegion;
    protected $unrelatedRegionWithRelatedAdminRegion;
    protected $unrelatedRegionLevel2WithRelatedAdminRegion;

    protected $relatedWorkingGroup;
    protected $relatedAdminWorkingGroup;
    protected $globalWorkingGroup;
    protected $unrelatedWorkingGroup;
    protected $unrelatedWorkingGroupLevel2WithRelatedAdminRegion;

    protected $foodsaver;

    final public function _before(): void
    {
        $this->foodsaverGateway = $this->tester->get(FoodsaverGateway::class);
        $this->regionGateway = $this->tester->get(RegionGateway::class);
        $this->session = $this->createMock(Session::class);
    }

    abstract protected function createCurrentUserUnitsInterface();

    final public function testGetHomeRegionIdNotLoginUser()
    {
        $this->session->expects($this->never())->method('set');
        $this->session->expects($this->once())->method('id')->willReturn(null);

        $current = $this->createCurrentUserUnitsInterface();

        $this->assertNull($current->getCurrentRegionId());
    }

    final public function testGetHomeRegionIdIsNotSet()
    {
        $foodsaverWithoutHomeRegion = $this->tester->createFoodsaver(null, ['bezirk_id' => 0]);

        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->once())->method('set');
        $this->session->expects($this->atLeastOnce())->method('id')->willReturn($foodsaverWithoutHomeRegion['id']);
        $this->session->expects($this->once())->method('role')->willReturn(Role::FOODSAVER);

        $current = $this->createCurrentUserUnitsInterface();

        $this->assertEquals(0, $current->getCurrentRegionId());
    }

    final public function testGetHomeRegionIdIsSet()
    {
        $this->masterRegion = $this->tester->createRegion();
        $this->homeRegion = $this->tester->createRegion(null, ['parent_id' => $this->masterRegion['id']]);
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => $this->homeRegion['id']]);

        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->once())->method('set');
        $this->session->expects($this->atLeastOnce())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->once())->method('role')->willReturn(Role::FOODSAVER);

        $current = $this->createCurrentUserUnitsInterface();

        $this->assertEquals($this->homeRegion['id'], $current->getCurrentRegionId());
    }

    protected function createUnitHierarchyForFoodsaver(bool $asAdmin = true)
    {
        $this->masterRegion = $this->tester->createRegion();
        $this->homeRegion = $this->tester->createRegion(null, ['master' => $this->masterRegion['id']]);
        $this->unrelatedRegion = $this->tester->createRegion(null, ['master' => $this->masterRegion['id']]);

        $this->relatedRegionWithUnrelatedParent = $this->tester->createRegion(null, ['parent_id' => $this->unrelatedRegion['id']]);
        $this->relatedRegion = $this->tester->createRegion(null, ['parent_id' => $this->homeRegion['id']]);
        $this->relatedAdminRegion = $this->tester->createRegion(null, ['parent_id' => $this->homeRegion['id']]);
        $this->unrelatedRegionWithRelatedAdminRegion = $this->tester->createRegion(null, ['parent_id' => $this->relatedAdminRegion['id']]);
        $this->unrelatedRegionLevel2WithRelatedAdminRegion = $this->tester->createRegion(null, ['parent_id' => $this->unrelatedRegionWithRelatedAdminRegion['id']]);

        $this->relatedWorkingGroup = $this->tester->createWorkingGroup(null, ['parent_id' => $this->masterRegion['id']]);
        $this->relatedAdminWorkingGroup = $this->tester->createWorkingGroup(null, ['parent_id' => $this->homeRegion['id']]);
        $this->globalWorkingGroup = $this->tester->createWorkingGroup(null);
        $this->unrelatedWorkingGroup = $this->tester->createWorkingGroup(null, ['parent_id' => $this->masterRegion['id']]);
        $this->unrelatedWorkingGroupLevel2WithRelatedAdminRegion = $this->tester->createRegion(null, ['parent_id' => $this->unrelatedRegionWithRelatedAdminRegion['id']]);

        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => $this->homeRegion['id']]);

        $this->tester->addRegionMember($this->relatedRegion['id'], $this->foodsaver['id']);
        $this->tester->addRegionMember($this->relatedRegionWithUnrelatedParent['id'], $this->foodsaver['id']);
        if ($asAdmin) {
            $this->tester->addRegionAdmin($this->relatedAdminRegion['id'], $this->foodsaver['id']);
        }
        $this->tester->addRegionMember($this->relatedWorkingGroup['id'], $this->foodsaver['id']);
        if ($asAdmin) {
            $this->tester->addRegionAdmin($this->relatedAdminWorkingGroup['id'], $this->foodsaver['id']);
        }
        $this->tester->addRegionMember($this->globalWorkingGroup['id'], $this->foodsaver['id']);
    }

    final public function testGetRegionsNotLoginUser()
    {
        $this->session->expects($this->never())->method('set');
        $this->session->expects($this->once())->method('id')->willReturn(null);

        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getRegions();
        $this->assertCount(0, $regions);
    }

    final public function testGetRegionsNoRelatedRegions()
    {
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => 0]);
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getRegions();
        $this->assertCount(0, $regions);
    }

    final public function testGetRegionsWithMembership()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getRegions();
        $this->assertContains(
            ['id' => $this->masterRegion['id'], 'name' => $this->masterRegion['name'], 'type' => $this->masterRegion['type'], 'parent_id' => $this->masterRegion['parent_id']],
            $regions);
        $this->assertContains(
            ['id' => $this->relatedWorkingGroup['id'], 'name' => $this->relatedWorkingGroup['name'], 'type' => $this->relatedWorkingGroup['type'], 'parent_id' => $this->relatedWorkingGroup['parent_id']],
            $regions);
    }

    final public function testListRegionIdsNotLoginUser()
    {
        $this->session->expects($this->never())->method('set');
        $this->session->expects($this->once())->method('id')->willReturn(null);

        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->listRegionIDs();
        $this->assertCount(0, $regions);
    }

    final public function testListRegionIdsNoRelatedRegions()
    {
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => 0]);
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->listRegionIDs();
        $this->assertCount(0, $regions);
    }

    final public function testListRegionIdsWithMembership()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->listRegionIDs();
        $this->assertContains($this->masterRegion['id'], $regions);
        $this->assertContains($this->homeRegion['id'], $regions);
        $this->assertContains($this->relatedRegion['id'], $regions);
        $this->assertContains($this->relatedRegionWithUnrelatedParent['id'], $regions);
        $this->assertContains($this->relatedAdminRegion['id'], $regions);
        $this->assertNotContains($this->unrelatedRegion['id'], $regions);

        $this->assertContains($this->relatedWorkingGroup['id'], $regions);
        $this->assertContains($this->relatedAdminWorkingGroup['id'], $regions);
        $this->assertContains($this->globalWorkingGroup['id'], $regions);
        $this->assertNotContains($this->unrelatedWorkingGroup['id'], $regions);
    }

    final public function testGetMyAmbassadorRegionIdsNotLoginUser()
    {
        $this->session->expects($this->never())->method('set');
        $this->session->expects($this->once())->method('id')->willReturn(null);

        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getMyAmbassadorRegionIds();
        $this->assertCount(0, $regions);
    }

    final public function testGetMyAmbassadorRegionIdsNoRelatedRegions()
    {
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => 0]);
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getMyAmbassadorRegionIds();
        $this->assertCount(0, $regions);
    }

    final public function testGetMyAmbassadorRegionIdsWithoutAdminMembership()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getMyAmbassadorRegionIds();
        $this->assertNotContains($this->relatedAdminRegion['id'], $regions);
        $this->assertNotContains($this->masterRegion['id'], $regions);
        $this->assertNotContains($this->homeRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegionWithUnrelatedParent['id'], $regions);
        $this->assertNotContains($this->unrelatedRegion['id'], $regions);

        $this->assertNotContains($this->relatedAdminWorkingGroup['id'], $regions);
        $this->assertNotContains($this->relatedWorkingGroup['id'], $regions);
        $this->assertNotContains($this->globalWorkingGroup['id'], $regions);
        $this->assertNotContains($this->unrelatedWorkingGroup['id'], $regions);
    }

    final public function testGetMyAmbassadorRegionIdsWithMembership()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getMyAmbassadorRegionIds();
        $this->assertContains($this->relatedAdminRegion['id'], $regions);
        $this->assertNotContains($this->masterRegion['id'], $regions);
        $this->assertNotContains($this->homeRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegionWithUnrelatedParent['id'], $regions);
        $this->assertNotContains($this->unrelatedRegion['id'], $regions);

        $this->assertContains($this->relatedAdminWorkingGroup['id'], $regions);
        $this->assertNotContains($this->relatedWorkingGroup['id'], $regions);
        $this->assertNotContains($this->globalWorkingGroup['id'], $regions);
        $this->assertNotContains($this->unrelatedWorkingGroup['id'], $regions);
    }

    final public function testGetMyAmbassadorRegionIdsWithMembershipNoWorkingGroups()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->once())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $regions = $current->getMyAmbassadorRegionIds(false);
        $this->assertContains($this->relatedAdminRegion['id'], $regions);
        $this->assertNotContains($this->masterRegion['id'], $regions);
        $this->assertNotContains($this->homeRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegion['id'], $regions);
        $this->assertNotContains($this->relatedRegionWithUnrelatedParent['id'], $regions);
        $this->assertNotContains($this->unrelatedRegion['id'], $regions);

        $this->assertNotContains($this->relatedAdminWorkingGroup['id'], $regions);
        $this->assertNotContains($this->relatedWorkingGroup['id'], $regions);
        $this->assertNotContains($this->globalWorkingGroup['id'], $regions);
        $this->assertNotContains($this->unrelatedWorkingGroup['id'], $regions);
    }

    final public function testIsAdminFor()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertTrue($current->isAdminFor(null));

        $this->assertTrue($current->isAdminFor($this->relatedAdminRegion['id']));
        $this->assertFalse($current->isAdminFor($this->masterRegion['id']));
        $this->assertFalse($current->isAdminFor($this->homeRegion['id']));
        $this->assertFalse($current->isAdminFor($this->relatedRegion['id']));
        $this->assertFalse($current->isAdminFor($this->relatedRegionWithUnrelatedParent['id']));
        $this->assertFalse($current->isAdminFor($this->unrelatedRegion['id']));

        $this->assertTrue($current->isAdminFor($this->relatedAdminWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->relatedWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->globalWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->unrelatedWorkingGroup['id']));
    }

    final public function testIsAdminForWithoutAdminMembership()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAdminFor(null));

        $this->assertFalse($current->isAdminFor($this->relatedAdminRegion['id']));
        $this->assertFalse($current->isAdminFor($this->masterRegion['id']));
        $this->assertFalse($current->isAdminFor($this->homeRegion['id']));
        $this->assertFalse($current->isAdminFor($this->relatedRegion['id']));
        $this->assertFalse($current->isAdminFor($this->relatedRegionWithUnrelatedParent['id']));
        $this->assertFalse($current->isAdminFor($this->unrelatedRegion['id']));

        $this->assertFalse($current->isAdminFor($this->relatedAdminWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->relatedWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->globalWorkingGroup['id']));
        $this->assertFalse($current->isAdminFor($this->unrelatedWorkingGroup['id']));
    }

    final public function testIsAmbassador()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertTrue($current->isAmbassador());
    }

    final public function testIsAmbassadorWithoutAdminMembership()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAmbassador());
    }

    final public function testMayBezirkNotLoginUser()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->never())->method('set');
        $this->session->expects($this->atLeast(0))->method('id')->willReturn(null);

        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->mayBezirk($this->homeRegion['id']));
    }

    final public function testMayBezirkNoUserRole()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(null);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->mayBezirk($this->homeRegion['id']));
    }

    final public function testMayBezirkAsOrga()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::ORGA);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertTrue($current->mayBezirk($this->homeRegion['id']));
    }

    final public function testMayBezirkAsMember()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertTrue($current->mayBezirk($this->homeRegion['id']));
    }

    final public function testMayBezirkAsNotMember()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->mayBezirk($this->unrelatedRegion['id']));
    }

    final public function testIsAmbassadorForRegionNotLoginUser()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn(null);
        $this->session->expects($this->any())->method('role')->willReturn(null);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedRegion['id']]));
        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedAdminRegion['id']]));
        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedWorkingGroup['id']]));
        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedAdminWorkingGroup['id']]));
    }

    final public function testIsAmbassadorForRegion()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedRegion['id']]));
        $this->assertTrue($current->isAmbassadorForRegion([$this->relatedAdminRegion['id']]));
        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedWorkingGroup['id']]));
        $this->assertTrue($current->isAmbassadorForRegion([$this->relatedAdminWorkingGroup['id']]));
    }

    final public function testIsAmbassadorForRegionWithoutWorkingGroups()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAmbassadorForRegion([$this->relatedAdminWorkingGroup['id']], false));
    }

    final public function testIsAmbassadorForRegionWithParents()
    {
        $this->createUnitHierarchyForFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturn(false);
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = $this->createCurrentUserUnitsInterface();

        $this->assertFalse($current->isAmbassadorForRegion([$this->unrelatedRegionWithRelatedAdminRegion['id']], false, false));
        $this->assertFalse($current->isAmbassadorForRegion([$this->unrelatedRegionLevel2WithRelatedAdminRegion['id']], false, false));
        $this->assertFalse($current->isAmbassadorForRegion([$this->unrelatedWorkingGroupLevel2WithRelatedAdminRegion['id']], false, false));

        $this->assertTrue($current->isAmbassadorForRegion([$this->unrelatedRegionWithRelatedAdminRegion['id']], false, true));
        $this->assertTrue($current->isAmbassadorForRegion([$this->unrelatedRegionLevel2WithRelatedAdminRegion['id']], false, true));
        $this->assertTrue($current->isAmbassadorForRegion([$this->unrelatedWorkingGroupLevel2WithRelatedAdminRegion['id']], false, true));
    }
}
