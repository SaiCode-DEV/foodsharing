<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\AchievementPermissions;
use Foodsharing\Permissions\RegionPermissions;
use Tests\Support\UnitTester;

final class RegionPermissionsTest extends Unit
{
    protected UnitTester $tester;
    protected RegionPermissions $regionPermissions;

    protected $userUnitMock;
    protected $sessionFake;

    public function _before(): void
    {
        $this->sessionFake = $this->makeEmpty(Session::class, ['mayRole' => fn ($role) => $role == Role::FOODSAVER]);
        $this->userUnitMock = $this->createMock(CurrentUserUnitsInterface::class);
        $this->regionPermissions = new RegionPermissions(
            $this->tester->get(RegionGateway::class),
            $this->sessionFake,
            $this->tester->get(GroupFunctionGateway::class),
            $this->userUnitMock,
            $this->tester->get(AchievementPermissions::class));
    }

    public function testMayNotJoinWorkGroup(): void
    {
        $region = $this->tester->createWorkingGroup('asdf');
        $this->tester->assertFalse($this->regionPermissions->mayJoinRegion($region['id']));
    }

    public function testMayJoinNormalRegion(): void
    {
        $region = $this->tester->createRegion();
        $this->tester->assertTrue($this->regionPermissions->mayJoinRegion($region['id']));
    }
}
