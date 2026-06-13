<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Foodsharing\Modules\Logout\LogoutController;
use Symfony\Component\HttpFoundation\Request;
use Tests\Support\UnitTester;

class LogoutControllerTest extends Unit
{
    protected UnitTester $tester;
    private LogoutController $controller;

    public function _before(): void
    {
        $this->controller = $this->tester->get(LogoutController::class);
    }

    public function testLogoutWithoutRefRedirectsToHome(): void
    {
        $response = $this->controller->index(new Request());
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
    }

    public function testLogoutFromRootPageStaysOnRoot(): void
    {
        $request = new Request(['ref' => '/']);
        $response = $this->controller->index($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
    }

    public function testLogoutFromPublicUrlStaysOnPage(): void
    {
        $request = new Request(['ref' => '/impressum']);
        $response = $this->controller->index($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/impressum', $response->headers->get('Location'));
    }

    public function testLogoutFromPublicPageParamStaysOnPage(): void
    {
        // 'content' is not in the private pages list
        $request = new Request(['ref' => '/?page=content']);
        $response = $this->controller->index($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/?page=content', $response->headers->get('Location'));
    }

    /**
     * @dataProvider privatePageProvider
     */
    public function testLogoutFromPrivatePageRedirectsToHome(string $page): void
    {
        $request = new Request(['ref' => '/?page=' . $page]);
        $response = $this->controller->index($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/', $response->headers->get('Location'));
    }

    public static function privatePageProvider(): array
    {
        return [
            'betrieb' => ['betrieb'],
            'bezirk' => ['bezirk'],
            'event' => ['event'],
            'foodsaver' => ['foodsaver'],
            'fsbetrieb' => ['fsbetrieb'],
            'groups' => ['groups'],
            'mailbox' => ['mailbox'],
            'message' => ['message'],
            'quiz' => ['quiz'],
            'report' => ['report'],
            'settings' => ['settings'],
            'store' => ['store'],
        ];
    }
}
