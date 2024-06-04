<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Group\GroupFunctionGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Permissions\RegionPermissions;
use Tests\Support\UnitTester;

final class RegionPermissionsTest extends Unit
{
    protected UnitTester $tester;
    protected RegionPermissions $regionPermissions;

    public function _before(): void
    {
        $mock = $this->makeEmpty(Session::class, ['mayRole' => fn ($role) => $role == Role::FOODSAVER]);
        $this->regionPermissions = new RegionPermissions(
            $this->tester->get(RegionGateway::class),
            $mock,
            $this->tester->get(GroupFunctionGateway::class),
            $mock);
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
