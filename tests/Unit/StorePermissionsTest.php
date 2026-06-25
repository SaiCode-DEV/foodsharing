<?php

declare(strict_types=1);

namespace Tests\Unit;

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Achievement\AchievementGateway;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\PassportGenerator\PassportGeneratorTransaction;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Store\StoreGateway;
use Foodsharing\Modules\Store\TeamStatus;
use Foodsharing\Modules\StoreChain\StoreChainGateway;
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
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(PassportGeneratorTransaction::class),
            $this->tester->get(StoreChainGateway::class),
        );
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

    public function testMayBecomeStoreManagerForRegularMember(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::Member);
        $this->assertTrue($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    public function testMayBecomeStoreManagerForJumper(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::WaitingList);
        $this->assertTrue($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    public function testCannotBecomeStoreManagerWhenNotInTeam(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::NoMember);
        $this->assertFalse($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    public function testCannotBecomeStoreManagerWhenOnlyApplied(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::Applied);
        $this->assertFalse($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    public function testCannotBecomeStoreManagerWhenOnlyInvited(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::Invited);
        $this->assertFalse($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    public function testCannotBecomeStoreManagerWithInsufficientRole(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::Member);
        $this->assertFalse($storePermissions->mayBecomeStoreManager(1, 99, Role::FOODSAVER));
    }

    public function testCannotBecomeStoreManagerWhenAlreadyManager(): void
    {
        $storePermissions = $this->buildStorePermissionsForManagerCheck(TeamStatus::Coordinator, [99]);
        $this->assertFalse($storePermissions->mayBecomeStoreManager(1, 99, Role::STORE_MANAGER));
    }

    private function configureSessionMock(array $roleToMay): void
    {
        $matcher = $this->exactly(count($roleToMay));

        $this->sessionMock->expects($matcher)->method('mayRole')
            ->willReturnCallback(fn ($role) => $roleToMay[$role->value]);
    }

    /**
     * Builds a StorePermissions instance with a mocked StoreGateway so that the team status of the
     * user under test and the list of current managers can be controlled. The remaining dependencies
     * used by the store manager count restriction are stubbed so that the restriction does not apply.
     */
    private function buildStorePermissionsForManagerCheck(int $teamStatus, array $currentManagers = []): StorePermissions
    {
        $storeGatewayMock = $this->createMock(StoreGateway::class);
        $storeGatewayMock->method('getStoreManagers')->willReturn($currentManagers);
        $storeGatewayMock->method('getUserTeamStatus')->willReturn($teamStatus);
        $storeGatewayMock->method('getStoreRegionId')->willReturn(1);

        $sessionMock = $this->createMock(Session::class);
        $sessionMock->method('mayRole')->willReturn(false);

        $groupFunctionGatewayMock = $this->createMock(GroupFunctionGateway::class);
        $groupFunctionGatewayMock->method('getRegionFunctionGroupId')->willReturn(0);

        $currentUserUnitsMock = $this->createMock(CurrentUserUnitsInterface::class);
        $currentUserUnitsMock->method('isAdminFor')->willReturn(false);

        return new StorePermissions(
            $storeGatewayMock,
            $sessionMock,
            $groupFunctionGatewayMock,
            $this->tester->get(ProfilePermissions::class),
            $this->regionGatewayMock,
            $currentUserUnitsMock,
            $this->tester->get(AchievementGateway::class),
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(PassportGeneratorTransaction::class),
            $this->tester->get(StoreChainGateway::class),
        );
    }
}
