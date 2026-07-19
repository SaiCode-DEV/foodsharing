<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\RegionPermissions;
use Foodsharing\Permissions\SearchPermissions;
use Tests\Support\UnitTester;

final class SearchPermissionsTest extends Unit
{
    protected UnitTester $tester;
    protected SearchPermissions $searchPermissions;

    protected $sessionFake;
    protected bool $isAdminOfFspTeamGroup = false;

    public function _before(): void
    {
        $this->sessionFake = $this->makeEmpty(Session::class, [
            'mayRole' => fn ($role) => $role === Role::FOODSAVER,
        ]);
        // Only the single überregional FSP team admin group (RegionIDs::FSP_TEAM_ADMIN_GROUP)
        // grants the privilege; admin of any other region must not.
        $currentUserUnitsFake = $this->makeEmpty(CurrentUserUnitsInterface::class, [
            'isAdminFor' => fn (int $regionId) => $regionId === RegionIDs::FSP_TEAM_ADMIN_GROUP && $this->isAdminOfFspTeamGroup,
        ]);
        $this->searchPermissions = new SearchPermissions(
            $this->sessionFake,
            $this->makeEmpty(GroupFunctionGateway::class),
            $this->createMock(RegionPermissions::class),
            $currentUserUnitsFake,
        );
    }

    public function testFspTeamAdminMaySearchAllFoodSharePoints(): void
    {
        $this->isAdminOfFspTeamGroup = true;
        $this->tester->assertTrue($this->searchPermissions->maySearchAllFoodSharePoints());
    }

    public function testRegularFoodsaverMayNotSearchAllFoodSharePoints(): void
    {
        $this->isAdminOfFspTeamGroup = false;
        $this->tester->assertFalse($this->searchPermissions->maySearchAllFoodSharePoints());
    }
}
