<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Settings\SettingsGateway;
use Tests\Support\UnitTester;

class SessionTestAdapter extends Session
{
    public function __construct(
        private Mem $mem,
        private FoodsaverGateway $foodsaverGateway,
        private RegionGateway $regionGateway,
        private LoginGateway $loginGateway,
        private SettingsGateway $settingsGateway,
        protected bool $initialized = false
    ) {
        parent::__construct($mem, $foodsaverGateway, $regionGateway, $loginGateway, $initialized);
    }

    public function setTestUser(Role $role)
    {
        $this->setId(1);
        $this->setAuthLevel($role);
    }
}

class SessionMayRoleTest extends Unit
{
    private ?SessionTestAdapter $session;
    protected UnitTester $tester;

    public function _before()
    {
        $this->session = new SessionTestAdapter(
            $this->tester->get(Mem::class),
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(RegionGateway::class),
            $this->tester->get(LoginGateway::class),
            $this->tester->get(SettingsGateway::class),
            true
        );
    }

    public function testDifferentRolesAndPermissions(): void
    {
        $this->session->setTestUser(Role::SITE_ADMIN);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertTrue($this->session->mayRole(Role::FOODSAVER));
        $this->assertTrue($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertTrue($this->session->mayRole(Role::AMBASSADOR));
        $this->assertTrue($this->session->mayRole(Role::ORGA));
        $this->assertTrue($this->session->mayRole(Role::SITE_ADMIN));

        $this->session->setTestUser(Role::ORGA);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertTrue($this->session->mayRole(Role::FOODSAVER));
        $this->assertTrue($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertTrue($this->session->mayRole(Role::AMBASSADOR));
        $this->assertTrue($this->session->mayRole(Role::ORGA));
        $this->assertFalse($this->session->mayRole(Role::SITE_ADMIN));

        $this->session->setTestUser(Role::AMBASSADOR);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertTrue($this->session->mayRole(Role::FOODSAVER));
        $this->assertTrue($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertTrue($this->session->mayRole(Role::AMBASSADOR));
        $this->assertFalse($this->session->mayRole(Role::ORGA));
        $this->assertFalse($this->session->mayRole(Role::SITE_ADMIN));

        $this->session->setTestUser(Role::STORE_MANAGER);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertTrue($this->session->mayRole(Role::FOODSAVER));
        $this->assertTrue($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertFalse($this->session->mayRole(Role::AMBASSADOR));
        $this->assertFalse($this->session->mayRole(Role::ORGA));
        $this->assertFalse($this->session->mayRole(Role::SITE_ADMIN));

        $this->session->setTestUser(Role::FOODSAVER);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertTrue($this->session->mayRole(Role::FOODSAVER));
        $this->assertFalse($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertFalse($this->session->mayRole(Role::AMBASSADOR));
        $this->assertFalse($this->session->mayRole(Role::ORGA));
        $this->assertFalse($this->session->mayRole(Role::SITE_ADMIN));

        $this->session->setTestUser(Role::FOODSHARER);
        $this->assertTrue($this->session->mayRole(Role::FOODSHARER));
        $this->assertFalse($this->session->mayRole(Role::FOODSAVER));
        $this->assertFalse($this->session->mayRole(Role::STORE_MANAGER));
        $this->assertFalse($this->session->mayRole(Role::AMBASSADOR));
        $this->assertFalse($this->session->mayRole(Role::ORGA));
        $this->assertFalse($this->session->mayRole(Role::SITE_ADMIN));
    }
}
