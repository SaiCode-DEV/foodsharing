<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Lib\Db\Mem;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Login\LoginGateway;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Support\UnitTester;

class SessionTestAdapter extends Session
{
    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly LoginGateway $loginGateway,
        private readonly Mem $mem
    ) {
        // initialize parent with test-mode flag true
        parent::__construct($foodsaverGateway, $loginGateway, $mem, true);

        // install mock session storage in the parent's protected property
        $sessionStorage = new MockArraySessionStorage();
        $this->symfonySession = new SymfonySession($sessionStorage);
        $this->symfonySession->start();
    }

    // Override init to avoid replacing our mock session during tests
    public function init($rememberMe = false)
    {
        // Intentionally left blank in tests to keep mock session
    }

    public function setTestUser(Role $role)
    {
        // Use the parent's symfonySession property directly
        $this->symfonySession->set('userId', 1);
        $this->symfonySession->set('role', $role);
        $this->set(Session::LAST_ACTIVITY, false);
    }
}

class SessionTest extends Unit
{
    private ?SessionTestAdapter $session = null;
    protected UnitTester $tester;

    public function _before()
    {
        $this->session = new SessionTestAdapter(
            $this->tester->get(FoodsaverGateway::class),
            $this->tester->get(LoginGateway::class),
            $this->tester->get(Mem::class)
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

    public function testCsrfTokenGenerationAndValidation(): void
    {
        // Ensure no csrf tokens initially
        $this->assertFalse($this->session->get('csrf'));

        // Generate a token and validate it
        $token = $this->session->generateCSRFToken();
        $this->assertIsString($token);
        $this->assertTrue($this->session->isValidCsrfToken($token));

        // Invalid token should be rejected
        $this->assertFalse($this->session->isValidCsrfToken('not-a-token'));

        // Generate multiple tokens and ensure they are all valid
        $token2 = $this->session->generateCSRFToken();
        $this->assertTrue($this->session->isValidCsrfToken($token));
        $this->assertTrue($this->session->isValidCsrfToken($token2));
    }

    public function testLoginRegistersSessionAndLogoutRemovesSession(): void
    {
        // Create foodsaver
        $fs = $this->tester->createFoodsaver();

        // Ensure PHP session id is present so the session handler registers it
        session_id('test-session-1');

        // Simulate a real login which will refresh session data and register the session
        $this->session->login($fs['id']);

        // After refreshFromDatabase, session should have client and login true
        $this->assertEquals($fs['id'], $this->session->id());
        $this->assertTrue($this->session->get('login'));

        $this->session->logout();

        // session user should be false after logout
        $this->assertFalse($this->session->get('user'));
    }

    public function testIsValidCsrfHeaderBehavior(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();

        // Anonymous user (no id) should bypass CSRF header requirement
        $this->assertTrue($this->session->isValidCsrfHeader($request));

        // Set a user id to simulate logged-in user
        $this->session->set('userId', 42);

        // Missing header should fail
        $this->assertFalse($this->session->isValidCsrfHeader($request));

        // With header but invalid token should fail
        $request->headers->set('x-csrf-token', 'invalid-token');
        $this->assertFalse($this->session->isValidCsrfHeader($request));

        // With valid token should pass
        $token = $this->session->generateCSRFToken();
        $request->headers->set('x-csrf-token', $token);
        $this->assertTrue($this->session->isValidCsrfHeader($request));
    }
}
