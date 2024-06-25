<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Unit;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Unit\CurrentUserUnitsSessionTransactions;
use Foodsharing\Modules\Unit\UserUnitsInformation;
use Tests\Support\UnitTester;

class CurrentUserUnitsSessionTransactionsTest extends CurrentUserUnitsInterfaceIntegrationTestSuite
{
    protected UnitTester $tester;

    protected function createCurrentUserUnitsInterface()
    {
        return new CurrentUserUnitsSessionTransactions($this->foodsaverGateway, $this->regionGateway, $this->achievementGateway, $this->session);
    }

    final public function testStoreRestoreFromSession()
    {
        $this->masterRegion = $this->tester->createRegion();
        $this->homeRegion = $this->tester->createRegion(null, ['parent_id' => $this->masterRegion['id']]);
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => $this->homeRegion['id']]);

        $this->session->expects($this->any())->method('has')->with('units_information')->willReturnOnConsecutiveCalls(false, true);

        // copy received to session
        $stored_value = null;
        $this->session->expects($this->once())->method('set')->with('units_information', self::callback(
            function ($value) use (&$stored_value) {
                $stored_value = $value;

                return true;
            }
        ));
        $this->session->expects($this->once())->method('get')->willReturnCallback(function () use (&$stored_value) { return $stored_value; }); // Read from session
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);

        $current = new CurrentUserUnitsSessionTransactions($this->foodsaverGateway, $this->regionGateway, $this->achievementGateway, $this->session);

        $this->assertEquals($this->homeRegion['id'], $current->getCurrentRegionId());
        $this->assertEquals($this->homeRegion['id'], $current->getCurrentRegionId());
    }

    final public function testSessionCacheExistsByCheckOfHomeRegionChanged()
    {
        $this->masterRegion = $this->tester->createRegion();
        $this->homeRegion = $this->tester->createRegion(null, ['parent_id' => $this->masterRegion['id']]);
        $this->foodsaver = $this->tester->createFoodsaver(null, ['bezirk_id' => $this->homeRegion['id']]);

        $this->session->expects($this->any())->method('has')->with('units_information')->willReturnOnConsecutiveCalls(false, true);
        $this->session->expects($this->once())->method('set')->with(); // insert in session

        $sessionContent = new UserUnitsInformation();
        $sessionContent->homeRegionId = 123;
        $this->session->expects($this->once())->method('get')->willReturn(serialize($sessionContent)); // Read from session
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);

        $current = new CurrentUserUnitsSessionTransactions($this->foodsaverGateway, $this->regionGateway, $this->achievementGateway, $this->session);

        $this->assertEquals($this->homeRegion['id'], $current->getCurrentRegionId());
        $this->assertEquals(123, $current->getCurrentRegionId());
    }

    final public function testMayBezirkAsMemberButSessionIsAsync()
    {
        $this->createUnitHierarchyForFoodsaver(false);
        $moveMembershipToOtherUser = $this->tester->createFoodsaver();
        $this->session->expects($this->any())->method('has')->with('units_information')->willReturnOnConsecutiveCalls(false, true);
        $this->session->expects($this->exactly(2))->method('set')->with(); // insert in session and update by force load

        $sessionContent = new UserUnitsInformation();
        $sessionContent->homeRegionId = 123;
        $sessionContent->unitsWithMembership = [$this->relatedRegion];
        $this->session->expects($this->once())->method('get')->willReturn(serialize($sessionContent)); // Read from session
        $this->session->expects($this->any())->method('id')->willReturn($this->foodsaver['id']);
        $this->session->expects($this->any())->method('role')->willReturn(Role::FOODSAVER);
        $current = new CurrentUserUnitsSessionTransactions($this->foodsaverGateway, $this->regionGateway, $this->achievementGateway, $this->session);

        // Load content from DB
        $this->assertTrue($current->mayBezirk($this->relatedRegion['id']));

        // Change DB without notification (Should not happen with single responsiblility of modules)
        $this->tester->updateInDatabase('fs_foodsaver_has_bezirk',
            ['foodsaver_id' => $moveMembershipToOtherUser['id']], ['bezirk_id' => $this->relatedRegion['id']]);

        // Should return false (removed entry from DB)
        $this->assertFalse($current->mayBezirk($this->relatedRegion['id']));
    }
}
