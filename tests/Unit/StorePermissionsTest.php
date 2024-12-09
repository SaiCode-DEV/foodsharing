<?php

declare(strict_types=1);

namespace Tests\Unit;

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Development\FeatureToggles\DependencyInjection\FeatureToggleChecker;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\ProfilePermissions;
use Foodsharing\Permissions\StorePermissions;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\UnitTester;

final class StorePermissionsTest extends Unit
{
    protected UnitTester $tester;
    protected MockObject $sessionMock;
    protected MockObject $regionGatewayMock;
    protected MockObject $currentUserUnitsMock;
    protected StorePermissions $storePermissions;

    public function _before(): void
    {
        $this->sessionMock = $this->createMock(Session::class);
        $this->currentUserUnitsMock = $this->createMock(CurrentUserUnitsInterface::class);
        $this->regionGatewayMock = $this->createMock(RegionGateway::class);
        $this->storePermissions = new StorePermissions(
            $this->tester->get(StoreGateway::class),
            $this->sessionMock,
            $this->tester->get(GroupFunctionGateway::class),
            $this->tester->get(ProfilePermissions::class),
            $this->regionGatewayMock,
            $this->currentUserUnitsMock,
            $this->tester->get(AchievementGateway::class),
            $this->tester->get(FeatureToggleChecker::class));
    }

    public function testListStoresLoadUserIdFromSession(): void
    {
        $this->sessionMock->expects($this->once())->method('id')->willReturn(10);
        $this->storePermissions->mayListStores();
    }

    public function testListStoresLoadUserIdFromSessionNoUserId(): void
    {
        $this->sessionMock->expects($this->once())->method('id')->willReturn(null);
        $this->assertFalse($this->storePermissions->mayListStores());
    }

    public function testListStoresForFoodSaverAndHigherIndependentFromVerificationStatus(): void
    {
        $this->sessionMock->expects($this->once())->method('mayRole')->with(Role::FOODSAVER)->will($this->returnValue(true));
        $this->assertTrue($this->storePermissions->mayListStores(1));
    }

    public function testListStoresForFoodSharer(): void
    {
        $this->sessionMock->expects($this->once())->method('mayRole')->with(Role::FOODSAVER)->willReturn(false);
        $this->assertFalse($this->storePermissions->mayListStores(1));
    }

    public function testCreatePermissionForFoodSharer(): void
    {
        $this->configureSessionMock([
            Role::ORGA->value => false,
            Role::STORE_MANAGER->value => false
        ]);
        $this->assertFalse($this->storePermissions->mayCreateStore(1));
    }

    public function testCreatePermissionForStoreManagerRegionIndependent(): void
    {
        $this->configureSessionMock([
            Role::ORGA->value => false,
            Role::STORE_MANAGER->value => true
        ]);
        $this->assertTrue($this->storePermissions->mayCreateStore());
    }

    public function testCreatePermissionForStoreManagerOfRegion(): void
    {
        $this->sessionMock->expects($this->once())->method('id')->willReturn(123);
        $this->configureSessionMock([
            Role::ORGA->value => false,
            Role::STORE_MANAGER->value => true
        ]);
        $this->regionGatewayMock->method('hasMember')->with(123, 1)->willReturn(true);
        $this->assertTrue($this->storePermissions->mayCreateStore(1));
    }

    public function testCreatePermissionForStoreManagerOfOtherRegion(): void
    {
        $this->sessionMock->expects($this->once())->method('id')->willReturn(123);
        $this->configureSessionMock([
            Role::ORGA->value => false,
            Role::STORE_MANAGER->value => true
        ]);
        $this->regionGatewayMock->method('hasMember')->with(123, 1)->willReturn(false);
        $this->assertFalse($this->storePermissions->mayCreateStore(1));
    }

    public function testCreatePermissionForStoreManagerOfInvalidRegion(): void
    {
        $this->sessionMock->expects($this->once())->method('id')->willReturn(123);
        $this->configureSessionMock([
            Role::ORGA->value => false,
            Role::STORE_MANAGER->value => true
        ]);
        $this->regionGatewayMock->method('hasMember')->with(123, 1234)->will($this->throwException(new DatabaseNoValueFoundException()));
        $this->assertFalse($this->storePermissions->mayCreateStore(1234));
    }

    public function testCreatePermissionForOrga(): void
    {
        $this->sessionMock->expects($this->once())->method('mayRole')->with(Role::ORGA)->willReturn(true);
        $this->assertTrue($this->storePermissions->mayCreateStore(1));
    }

    private function configureSessionMock(array $roleToMay): void
    {
        $matcher = $this->exactly(count($roleToMay));

        $this->sessionMock->expects($matcher)->method('mayRole')
            ->willReturnCallback(fn ($role) => $roleToMay[$role->value]);
    }
}
